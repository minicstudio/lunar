<?php

use Illuminate\Support\Str;
use Livewire\Livewire;
use Lunar\Admin\Filament\Resources\CustomerResource;
use Lunar\Admin\Filament\Resources\OrderResource\Pages\ManageOrder;
use Lunar\Admin\Livewire\Components\ActivityLogFeed as ActivityLogFeedComponent;
use Lunar\Admin\Support\Extending\ViewPageExtension;
use Lunar\Admin\Support\Facades\LunarPanel;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\Base\ValueObjects\Cart\TaxBreakdownAmount;
use Lunar\DataTypes\Price;
use Lunar\Facades\Pricing;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\Customer;
use Lunar\Models\Language;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Lunar\Models\OrderLine;
use Lunar\Models\Price as ModelsPrice;
use Lunar\Models\ProductVariant;
use Lunar\Models\Transaction;
use Lunar\Tests\Admin\Feature\Filament\TestCase;

uses(TestCase::class)
    ->group('resource.order');

beforeEach(function () {
    $this->asStaff();

    $currency = Currency::factory()->create([
        'default' => true,
    ]);

    $country = Country::factory()->create();

    Language::factory()->create();

    $this->order = Order::factory()
        ->for(Customer::factory())
        ->has(OrderAddress::factory()->state([
            'type' => 'shipping',
            'country_id' => $country->id,
        ]), 'shippingAddress')
        ->has(OrderAddress::factory()->state([
            'type' => 'billing',
            'country_id' => $country->id,
        ]), 'billingAddress')
        ->create([
            'currency_code' => $currency->code,
            'meta' => [
                'additional_info' => Str::random(),
            ],
        ]);

});

it('can render order manage page', function () {
    $this->order->customer->update([
        'title' => 'Customer category',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);

    $currency = Currency::getDefault();

    $variants = ProductVariant::factory(5)
        ->has(ModelsPrice::factory()->state([
            'currency_id' => $currency->id,
        ]))->create();

    $lines = collect();

    foreach ($variants as $variant) {
        $quantity = rand(1, 5);

        $pricing = Pricing::for($variant, $quantity)->get();
        $price = $pricing->matched->price->value;
        $subTotal = $price * $quantity;
        $tax = (int) ($subTotal * .2);
        $options = $variant->values->map(fn ($value) => $value->translate('name'));

        $itemTax = (new TaxBreakdown);
        $itemTax->addAmount(new TaxBreakdownAmount(
            price: new Price(
                value: $tax,
                currency: $currency
            ),
            identifier: $currency->code,
            description: 'VAT',
            percentage: 20,
        ));

        $lines->push([
            'quantity' => $quantity,
            'purchasable_type' => $variant->getMorphClass(),
            'purchasable_id' => $variant->id,
            'type' => 'physical',
            'description' => $variant->product->translateAttribute('name'),
            'identifier' => $variant->sku,
            'option' => $options->join(', '),
            'unit_price' => $price,
            'unit_quantity' => $variant->unit_quantity,
            'sub_total' => $subTotal,
            'discount_total' => 0,
            'tax_total' => $tax,
            'total' => $subTotal + $tax,
            'tax_breakdown' => $itemTax,
        ]);
    }

    $this->order->transactions()->save(Transaction::factory()->create([
        'driver' => 'offline',
        'type' => 'capture',
        'amount' => $lines->sum('total'),
    ]));

    $lines = $this->order->lines()->createMany($lines->toArray());

    $firstItem = $lines->first();
    $secondItem = $lines->skip(1)->take(1)->first();

    expect($firstItem)
        ->not->toBe($secondItem);

    Livewire::test(ManageOrder::class, [
        'record' => $this->order->getRouteKey(),
    ])
        ->assertSuccessful()
        ->assertSeeLivewire(ActivityLogFeedComponent::class)
        ->assertSee($this->order->tags)
        ->assertSee($this->order->shippingAddress->line_one)
        ->assertSee($this->order->shippingAddress->line_one)
        ->assertSee($this->order->total->formatted)
        ->assertSee('Jane Doe')
        ->assertDontSee('Customer category Jane Doe')
        ->assertSee(CustomerResource::getUrl('edit', ['record' => $this->order->customer->id]))
        ->assertSee(__('lunarpanel::order.transactions.capture'))
        ->assertSee($this->order->captures->first()->amount->formatted)
        ->assertSee($this->order->meta['additional_info'])
        ->assertSee($firstItem->total->formatted)
        ->assertSee($firstItem->sub_total->formatted)
        ->assertSee($secondItem->total->formatted)
        ->assertSee($this->order->reference);
});

it('shows a preorder notice under the product name', function () {
    $currency = Currency::getDefault();

    $preorder = ProductVariant::factory()
        ->has(ModelsPrice::factory()->state([
            'currency_id' => $currency->id,
        ]))
        ->create([
            'purchasable' => 'preorder',
        ]);

    $inStock = ProductVariant::factory()
        ->has(ModelsPrice::factory()->state([
            'currency_id' => $currency->id,
        ]))
        ->create([
            'purchasable' => 'in_stock',
        ]);

    foreach ([$preorder, $inStock] as $variant) {
        $taxBreakdown = new TaxBreakdown;
        $taxBreakdown->addAmount(new TaxBreakdownAmount(
            price: new Price(value: 0, currency: $currency),
            identifier: $currency->code,
            description: 'VAT',
            percentage: 0,
        ));

        $this->order->lines()->create([
            'quantity' => 1,
            'purchasable_type' => $variant->getMorphClass(),
            'purchasable_id' => $variant->id,
            'type' => 'physical',
            'description' => $variant->product->translateAttribute('name'),
            'identifier' => $variant->sku,
            'unit_price' => 1000,
            'unit_quantity' => 1,
            'sub_total' => 1000,
            'discount_total' => 0,
            'tax_total' => 0,
            'total' => 1000,
            'tax_breakdown' => $taxBreakdown,
        ]);
    }

    $notice = __('lunarpanel::order.infolist.preorder.message');

    $html = Livewire::test(ManageOrder::class, [
        'record' => $this->order->getRouteKey(),
    ])
        ->assertSee($preorder->product->translateAttribute('name'))
        ->assertSee($inStock->product->translateAttribute('name'))
        ->html();

    expect(substr_count($html, $notice))->toBe(1);
});

it('can download order pdf', function () {
    Livewire::test(ManageOrder::class, [
        'record' => $this->order->getRouteKey(),
    ])
        ->assertActionExists('download_pdf')
        ->callAction('download_pdf')
        ->assertFileDownloaded("Order-{$this->order->reference}.pdf");
});

it('can update order status', function () {
    $status = collect(config('lunar.orders.statuses', []))
        ->keys()
        ->reject(fn ($status) => $status == $this->order->status)
        ->random();

    Livewire::test(ManageOrder::class, [
        'record' => $this->order->getRouteKey(),
    ])
        ->assertActionExists('update_status')
        ->callAction('update_status', [
            'status' => $status,
        ]);

    expect($this->order->refresh())
        ->status->toBe($status);
});

it('displays boolean order meta values as localized yes/no', function () {
    $this->order->update([
        'meta' => [
            'express_shipping' => true,
            'gift_message' => false,
        ],
    ]);

    Livewire::test(ManageOrder::class, [
        'record' => $this->order->getRouteKey(),
    ])
        ->assertSuccessful()
        ->assertSchemaComponentStateSet('meta.meta_express_shipping', __('lunarpanel::global.yes'))
        ->assertSchemaComponentStateSet('meta.meta_gift_message', __('lunarpanel::global.no'));
});

it('does not display numeric order meta values as yes/no', function () {
    $this->order->update([
        'meta' => [
            'parcel_count' => 1,
            'failed_attempts' => 0,
            'floor' => '1',
        ],
    ]);

    Livewire::test(ManageOrder::class, [
        'record' => $this->order->getRouteKey(),
    ])
        ->assertSuccessful()
        ->assertSchemaComponentStateSet('meta.meta_parcel_count', 1)
        ->assertSchemaComponentStateSet('meta.meta_failed_attempts', 0)
        ->assertSchemaComponentStateSet('meta.meta_floor', '1');
});

it('displays shipping and billing order meta in separate sections', function () {
    $this->order->update([
        'meta' => [
            'shippingType' => 'courier',
            'shipping_zone' => 'Europe',
            'awb' => '81155794566',
            'payment_option' => 'cash-on-delivery',
            'isBillingSameAsShipping' => true,
            'billing_series' => 'ABC',
            'billing_number' => '000123',
            'language_locale' => 'hu',
        ],
    ]);

    Livewire::test(ManageOrder::class, [
        'record' => $this->order->getRouteKey(),
    ])
        ->assertSuccessful()
        ->assertSee(__('lunarpanel::order.infolist.shipping_details.label'))
        ->assertSee(__('lunarpanel::order.infolist.billing_details.label'))
        ->assertSee(__('lunarpanel::order.infolist.meta.shippingType'))
        ->assertSee(__('lunarpanel::order.infolist.meta.payment_option'))
        ->assertSchemaComponentStateSet('shipping_details.meta_shippingType', __('lunarpanel::order.infolist.shipping_types.courier'))
        ->assertSchemaComponentStateSet('shipping_details.meta_shipping_zone', 'Europe')
        ->assertSchemaComponentStateSet('shipping_details.meta_awb', '81155794566')
        ->assertSchemaComponentStateSet('billing_details.meta_payment_option', __('lunarpanel::order.infolist.payment_methods.cash-on-delivery'))
        ->assertSchemaComponentStateSet('billing_details.meta_isBillingSameAsShipping', __('lunarpanel::global.yes'))
        ->assertSchemaComponentStateSet('billing_details.meta_billing_series', 'ABC')
        ->assertSchemaComponentStateSet('billing_details.meta_billing_number', '000123')
        ->assertSchemaComponentStateSet('customer_details.meta_language_locale', 'hu')
        ->assertSchemaComponentDoesNotExist('meta.meta_language_locale')
        ->assertSchemaComponentDoesNotExist('meta.meta_awb')
        ->assertSchemaComponentDoesNotExist('meta.meta_payment_option');
});

it('keeps unknown shipping and billing meta values as they are', function () {
    $this->order->update([
        'meta' => [
            'shippingType' => 'drone',
            'payment_option' => 'custom-gateway',
        ],
    ]);

    Livewire::test(ManageOrder::class, [
        'record' => $this->order->getRouteKey(),
    ])
        ->assertSuccessful()
        ->assertSchemaComponentStateSet('shipping_details.meta_shippingType', 'drone')
        ->assertSchemaComponentStateSet('billing_details.meta_payment_option', 'custom-gateway');
});

it('displays customer metadata below the customer type', function (bool $isGuest) {
    $this->order->update([
        'meta' => [
            'is_guest' => $isGuest,
            'language_locale' => 'hu',
        ],
    ]);

    Livewire::test(ManageOrder::class, [
        'record' => $this->order->getRouteKey(),
    ])
        ->assertSuccessful()
        ->assertSeeInOrder([
            __('lunarpanel::order.infolist.new_returning.label'),
            __('lunarpanel::order.infolist.meta.is_guest'),
            __('lunarpanel::order.infolist.meta.language_locale'),
            __('lunarpanel::order.infolist.status.label'),
        ])
        ->assertSchemaComponentStateSet('customer_details.meta_is_guest', __($isGuest ? 'lunarpanel::global.yes' : 'lunarpanel::global.no'))
        ->assertSchemaComponentStateSet('customer_details.meta_language_locale', 'hu')
        ->assertSchemaComponentDoesNotExist('meta.meta_is_guest')
        ->assertSchemaComponentDoesNotExist('meta.meta_language_locale')
        ->assertSchemaComponentStateSet('meta.no_additional_info', __('lunarpanel::order.infolist.no_additional_info.label'));
})->with([true, false]);

it('hides internal order metadata and preserves unclassified metadata', function () {
    $this->order->update([
        'meta' => [
            'stashed_courier_address' => ['line_one' => 'Hidden courier address'],
            'stashed_locker_address' => ['line_one' => 'Hidden locker address'],
            'insights_purchase_pushed_at' => '2026-10-01 12:34:56',
            'preloaded_from_order_id' => 123,
            'gtm_purchase_event_id' => 'hidden-purchase-event',
            'gtm_purchase_datalayer_pushed_at' => '2026-10-01 12:34:56',
            'gtm_ga4_purchase_fallback_sent_at' => '2026-10-01 12:34:56',
            'gtm_refund_sent_at' => '2026-10-01 12:34:56',
            'ga4_client_id' => '123456789.1234567890',
            'ga4_session_id' => '1234567890',
            'ga4_engagement_time_msec' => 1500,
            'gift_slot_allocations' => ['12' => ['34' => 2]],
            'custom_note' => 'Keep this note',
        ],
    ]);

    Livewire::test(ManageOrder::class, [
        'record' => $this->order->getRouteKey(),
    ])
        ->assertSuccessful()
        ->assertSchemaComponentDoesNotExist('meta.meta_stashed_courier_address')
        ->assertSchemaComponentDoesNotExist('meta.meta_stashed_locker_address')
        ->assertSchemaComponentDoesNotExist('meta.meta_insights_purchase_pushed_at')
        ->assertSchemaComponentDoesNotExist('meta.meta_preloaded_from_order_id')
        ->assertSchemaComponentDoesNotExist('meta.meta_gtm_purchase_event_id')
        ->assertSchemaComponentDoesNotExist('meta.meta_gtm_purchase_datalayer_pushed_at')
        ->assertSchemaComponentDoesNotExist('meta.meta_gtm_ga4_purchase_fallback_sent_at')
        ->assertSchemaComponentDoesNotExist('meta.meta_gtm_refund_sent_at')
        ->assertSchemaComponentDoesNotExist('meta.meta_ga4_client_id')
        ->assertSchemaComponentDoesNotExist('meta.meta_ga4_session_id')
        ->assertSchemaComponentDoesNotExist('meta.meta_ga4_engagement_time_msec')
        ->assertSchemaComponentDoesNotExist('meta.meta_gift_slot_allocations')
        ->assertDontSee('Hidden courier address')
        ->assertDontSee('Hidden locker address')
        ->assertDontSee('hidden-purchase-event')
        ->assertSchemaComponentStateSet('meta.meta_custom_note', 'Keep this note')
        ->assertSchemaComponentDoesNotExist('customer_details.meta_is_guest')
        ->assertSchemaComponentDoesNotExist('customer_details.meta_language_locale')
        ->assertSchemaComponentHidden('personalization');
});

it('displays personalization from order lines as separate stacked items', function () {
    $value = '<script>alert("gift")</script>';

    $this->order->update([
        'meta' => ['personalization' => ['1_MUG_BLUE' => 'Outdated cart text']],
    ]);

    foreach (['Anna', $value] as $index => $text) {
        OrderLine::factory()->for($this->order)->create([
            'description' => 'Engraved mug',
            'identifier' => 'MUG_BLUE',
            'quantity' => $index + 2,
            'meta' => ['personalization' => $text],
        ]);
    }

    OrderLine::factory()->for($this->order)->create([
        'description' => 'Plain mug',
        'meta' => [],
    ]);

    Livewire::test(ManageOrder::class, [
        'record' => $this->order->getRouteKey(),
    ])
        ->assertSuccessful()
        ->assertSchemaComponentVisible('personalization')
        ->assertSchemaComponentStateSet('personalization.personalization_items', [
            ['product' => 'Engraved mug', 'sku' => 'MUG_BLUE', 'quantity' => 2, 'value' => 'Anna'],
            ['product' => 'Engraved mug', 'sku' => 'MUG_BLUE', 'quantity' => 3, 'value' => $value],
        ])
        ->assertSeeInOrder(['Engraved mug', 'MUG_BLUE', '2', 'Anna'])
        ->assertSee($value)
        ->assertDontSee($value, false)
        ->assertDontSee('Outdated cart text')
        ->assertSchemaComponentDoesNotExist('meta.meta_personalization');
});

it('supports legacy personalization summaries and retains unmatched values', function () {
    $this->order->update([
        'meta' => [
            'personalization' => [
                '1_MUG_BLUE' => 'Anna',
                '2_MUG_BLUE' => 'Béla',
                '3_REMOVED_SKU' => 'Legacy text',
            ],
        ],
    ]);

    foreach ([2, 3] as $quantity) {
        OrderLine::factory()->for($this->order)->create([
            'description' => 'Engraved mug',
            'identifier' => 'MUG_BLUE',
            'quantity' => $quantity,
            'meta' => [],
        ]);
    }

    Livewire::test(ManageOrder::class, [
        'record' => $this->order->getRouteKey(),
    ])
        ->assertSuccessful()
        ->assertSchemaComponentStateSet('personalization.personalization_items', [
            ['product' => 'Engraved mug', 'sku' => 'MUG_BLUE', 'quantity' => 2, 'value' => 'Anna'],
            ['product' => 'Engraved mug', 'sku' => 'MUG_BLUE', 'quantity' => 3, 'value' => 'Béla'],
            ['product' => null, 'sku' => 'REMOVED_SKU', 'quantity' => null, 'value' => 'Legacy text'],
        ])
        ->assertSee('Legacy text')
        ->assertSchemaComponentDoesNotExist('meta.meta_personalization');
});

it('displays line personalization without an order level summary', function () {
    OrderLine::factory()->for($this->order)->create([
        'description' => 'Personalized gift',
        'identifier' => 'GIFT',
        'quantity' => 4,
        'meta' => ['personalization' => '0'],
    ]);

    Livewire::test(ManageOrder::class, [
        'record' => $this->order->getRouteKey(),
    ])
        ->assertSuccessful()
        ->assertSchemaComponentVisible('personalization')
        ->assertSchemaComponentStateSet('personalization.personalization_items', [
            ['product' => 'Personalized gift', 'sku' => 'GIFT', 'quantity' => 4, 'value' => '0'],
        ]);
});

it('hides shipping and billing details sections when the order has no matching meta', function () {
    Livewire::test(ManageOrder::class, [
        'record' => $this->order->getRouteKey(),
    ])
        ->assertSuccessful()
        ->assertSchemaComponentHidden('shipping_details')
        ->assertSchemaComponentHidden('billing_details')
        ->assertSchemaComponentStateSet('meta.meta_additional_info', $this->order->meta['additional_info']);
});

it('shows no additional information when all order meta is grouped', function () {
    $this->order->update([
        'meta' => [
            'shippingType' => 'locker',
            'payment_option' => 'stripe-card',
        ],
    ]);

    Livewire::test(ManageOrder::class, [
        'record' => $this->order->getRouteKey(),
    ])
        ->assertSuccessful()
        ->assertSchemaComponentVisible('shipping_details')
        ->assertSchemaComponentVisible('billing_details')
        ->assertSchemaComponentStateSet('meta.no_additional_info', __('lunarpanel::order.infolist.no_additional_info.label'));
});

it('can extend the shipping and billing order meta keys', function () {
    $extension = new class extends ViewPageExtension
    {
        public function extendShippingOrderMetaKeys(array $keys): array
        {
            return [...$keys, 'delivery_date'];
        }

        public function extendBillingOrderMetaKeys(array $keys): array
        {
            return [...$keys, 'vat_number'];
        }

        public function extendHiddenOrderMetaKeys(array $keys): array
        {
            return [...$keys, 'awb'];
        }
    };

    LunarPanel::extensions([
        ManageOrder::class => $extension::class,
    ]);

    $this->order->update([
        'meta' => [
            'awb' => '81155794566',
            'delivery_date' => '2026-10-10',
            'vat_number' => 'RO123456',
        ],
    ]);

    Livewire::test(ManageOrder::class, [
        'record' => $this->order->getRouteKey(),
    ])
        ->assertSuccessful()
        ->assertSchemaComponentStateSet('shipping_details.meta_delivery_date', '2026-10-10')
        ->assertSchemaComponentStateSet('billing_details.meta_vat_number', 'RO123456')
        ->assertSchemaComponentDoesNotExist('shipping_details.meta_awb')
        ->assertSchemaComponentDoesNotExist('meta.meta_awb')
        ->assertSchemaComponentDoesNotExist('meta.meta_delivery_date')
        ->assertSchemaComponentDoesNotExist('meta.meta_vat_number');
});
