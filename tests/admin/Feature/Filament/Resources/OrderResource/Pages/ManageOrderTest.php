<?php

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Lunar\Admin\Filament\Resources\CustomerResource;
use Lunar\Admin\Filament\Resources\OrderResource\Pages\Components\OrderItemsTable;
use Lunar\Admin\Filament\Resources\OrderResource\Pages\ManageOrder;
use Lunar\Admin\Livewire\Components\ActivityLogFeed as ActivityLogFeedComponent;
use Lunar\Admin\Support\Extending\ViewPageExtension;
use Lunar\Admin\Support\Facades\LunarPanel;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\Base\ValueObjects\Cart\TaxBreakdownAmount;
use Lunar\DataTypes\Price;
use Lunar\DataTypes\ShippingOption;
use Lunar\DiscountTypes\AdvancedAmountOff;
use Lunar\Facades\Pricing;
use Lunar\Facades\ShippingManifest;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\Customer;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Discount;
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
        ->assertSee($this->order->reference)
        ->assertSee(__('lunarpanel::order.infolist.alert.product_line_gates.paid'));
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

it('does not offer a refund action for captured payments', function () {
    Transaction::factory()->for($this->order)->create([
        'type' => 'capture',
        'success' => true,
        'driver' => 'offline',
        'amount' => 5000,
    ]);

    Livewire::test(ManageOrder::class, [
        'record' => $this->order->getRouteKey(),
    ])
        ->assertSuccessful()
        ->assertActionDoesNotExist('refund');
});

it('lists the meta of BT iPay card payments not shown elsewhere on the transaction card', function () {
    config([
        'lunar.payments.types.bt-ipay' => ['driver' => 'offline'],
        'lunar.panel.timezone' => 'Europe/Bucharest',
    ]);

    Transaction::factory()->for($this->order)->create([
        'type' => 'capture',
        'success' => true,
        'driver' => 'bt-ipay',
        'amount' => 12000,
        'reference' => '111aa4b9-baeb-4676-bbde-c5e4d5070cce',
        'meta' => [
            'bt_order_number' => '00000042-1',
            'bt_order_id' => '111aa4b9-baeb-4676-bbde-c5e4d5070cce',
            'installments' => 6,
            'payment_way' => 'CARD',
            'action_code' => 0,
            'approved_amount' => 12000,
            'deposited_amount' => 12000,
            'loyalty_amount' => 1800,
            'reverse_requested_at' => '2026-10-21T10:15:00+00:00',
            'reverse_trigger' => 'amount_mismatch',
        ],
    ]);

    $currency = Currency::getDefault();

    Livewire::test(ManageOrder::class, [
        'record' => $this->order->getRouteKey(),
    ])
        ->assertSuccessful()
        ->assertSeeInOrder([__('lunarpanel::order.transactions.meta.bt_order_number'), '00000042-1'])
        ->assertSeeInOrder([__('lunarpanel::order.transactions.meta.installments'), '6'])
        ->assertSeeInOrder([__('lunarpanel::order.transactions.meta.payment_way'), 'CARD'])
        ->assertSeeInOrder([__('lunarpanel::order.transactions.meta.action_code'), '0'])
        ->assertSeeInOrder([__('lunarpanel::order.transactions.meta.approved_amount'), (new Price(12000, $currency))->formatted])
        ->assertSeeInOrder([__('lunarpanel::order.transactions.meta.loyalty_amount'), (new Price(1800, $currency))->formatted])
        ->assertSeeInOrder([__('lunarpanel::order.transactions.meta.reverse_requested_at'), '2026-10-21 13:15:00'])
        ->assertSeeInOrder([__('lunarpanel::order.transactions.meta.reverse_trigger'), 'amount_mismatch'])
        ->assertDontSee('Bt Order Id')
        ->assertDontSee('Deposited Amount');
});

it('does not list the transaction meta of other payment drivers', function () {
    Transaction::factory()->for($this->order)->create([
        'type' => 'capture',
        'success' => true,
        'driver' => 'offline',
        'meta' => [
            'installments' => 6,
            'payment_way' => 'CARD',
        ],
    ]);

    Livewire::test(ManageOrder::class, [
        'record' => $this->order->getRouteKey(),
    ])
        ->assertSuccessful()
        ->assertDontSee(__('lunarpanel::order.transactions.meta.installments'))
        ->assertDontSee(__('lunarpanel::order.transactions.meta.payment_way'));
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

it('hides the product line gate shout when the order can be edited', function () {
    Livewire::test(ManageOrder::class, [
        'record' => $this->order->getRouteKey(),
    ])
        ->assertSuccessful()
        ->assertDontSee(__('lunarpanel::order.infolist.alert.product_line_gates.paid'))
        ->assertDontSee(__('lunarpanel::order.infolist.alert.product_line_gates.erp'))
        ->assertDontSee(__('lunarpanel::order.infolist.alert.product_line_gates.invoice'))
        ->assertDontSee(__('lunarpanel::order.infolist.alert.product_line_gates.awb'));
});

it('has product line editing copy in every admin locale', function () {
    $keys = [
        'lunarpanel::order.infolist.alert.product_line_gates.paid',
        'lunarpanel::order.infolist.alert.product_line_gates.erp',
        'lunarpanel::order.infolist.alert.product_line_gates.invoice',
        'lunarpanel::order.infolist.alert.product_line_gates.awb',
        'lunarpanel::order.action.add_product.label',
        'lunarpanel::order.action.add_product.product.label',
        'lunarpanel::order.action.add_product.variant.label',
        'lunarpanel::order.action.add_product.quantity.label',
        'lunarpanel::order.action.add_product.notification',
        'lunarpanel::order.action.update_quantity.label',
        'lunarpanel::order.action.update_quantity.quantity.label',
        'lunarpanel::order.action.update_quantity.notification',
        'lunarpanel::order.action.remove_product.label',
        'lunarpanel::order.action.remove_product.confirmation',
        'lunarpanel::order.action.remove_product.notification',
    ];

    foreach (['ar', 'bg', 'de', 'es', 'fa', 'fr', 'hr', 'hu', 'mn', 'nl', 'pl', 'pt_BR', 'ro', 'tr', 'vi', 'en'] as $locale) {
        foreach ($keys as $key) {
            expect(Lang::has($key, $locale, false))->toBeTrue();
        }
    }

    app()->setLocale('en');

    expect(__('lunarpanel::order.infolist.alert.product_line_gates.paid'))->toContain('Paid')
        ->and(__('lunarpanel::order.infolist.alert.product_line_gates.paid'))->toContain('cannot be modified')
        ->and(__('lunarpanel::order.infolist.alert.product_line_gates.erp'))->toContain('ERP')
        ->and(__('lunarpanel::order.infolist.alert.product_line_gates.invoice'))->toContain('Invoice')
        ->and(__('lunarpanel::order.infolist.alert.product_line_gates.awb'))->toContain('AWB');

    app()->setLocale('hu');

    expect(__('lunarpanel::order.infolist.alert.product_line_gates.paid'))->toContain('Fizetve')
        ->and(__('lunarpanel::order.infolist.alert.product_line_gates.paid'))->toContain('nem módosítható')
        ->and(__('lunarpanel::order.infolist.alert.product_line_gates.erp'))->toContain('ERP')
        ->and(__('lunarpanel::order.infolist.alert.product_line_gates.invoice'))->toContain('Számla')
        ->and(__('lunarpanel::order.infolist.alert.product_line_gates.awb'))->toContain('AWB');
});

it('has split order copy in every admin locale', function () {
    $keys = [
        'lunarpanel::order.infolist.alert.product_line_gates.split',
        'lunarpanel::order.infolist.alert.split_erp_warning',
        'lunarpanel::order.infolist.split_from.label',
        'lunarpanel::order.infolist.split_into.label',
        'lunarpanel::order.statuses.split',
        'lunarpanel::order.action.split_order.label',
        'lunarpanel::order.action.split_order.heading',
        'lunarpanel::order.action.split_order.helper',
        'lunarpanel::order.action.split_order.products',
        'lunarpanel::order.action.split_order.preview',
        'lunarpanel::order.action.split_order.empty_preview',
        'lunarpanel::order.action.split_order.new_order',
        'lunarpanel::order.action.split_order.move_to_order',
        'lunarpanel::order.action.split_order.divide_quantity',
        'lunarpanel::order.action.split_order.price',
        'lunarpanel::order.action.split_order.price_changed',
        'lunarpanel::order.action.split_order.original_price',
        'lunarpanel::order.action.split_order.current_price',
        'lunarpanel::order.action.split_order.shipping',
        'lunarpanel::order.action.split_order.shipping_unavailable',
        'lunarpanel::order.action.split_order.payment_method',
        'lunarpanel::order.action.split_order.order_total',
        'lunarpanel::order.action.split_order.coupon',
        'lunarpanel::order.action.split_order.loyalty',
        'lunarpanel::order.action.split_order.do_not_apply',
        'lunarpanel::order.action.split_order.confirm',
        'lunarpanel::order.action.split_order.notification',
        'lunarpanel::order.action.split_order.gates.shipping_required',
        'lunarpanel::order.action.split_order.gates.payment_required',
        'lunarpanel::order.action.split_order.gates.loyalty_spent',
        'lunarpanel::order.action.split_order.gates.loyalty_cannot_accept',
        'lunarpanel::order.action.split_order.gates.too_few_targets',
    ];

    foreach (['ar', 'bg', 'de', 'es', 'fa', 'fr', 'hr', 'hu', 'mn', 'nl', 'pl', 'pt_BR', 'ro', 'tr', 'vi', 'en'] as $locale) {
        foreach ($keys as $key) {
            expect(Lang::has($key, $locale, false))->toBeTrue();
        }
    }

    app()->setLocale('en');

    expect(__('lunarpanel::order.action.split_order.label'))->toBe('Split order')
        ->and(__('lunarpanel::order.action.split_order.shipping'))->toBe('Shipping')
        ->and(__('lunarpanel::order.action.split_order.coupon'))->toBe('Coupon')
        ->and(__('lunarpanel::order.action.split_order.products'))->toBe('Products')
        ->and(__('lunarpanel::order.action.split_order.preview'))->toBe('Preview')
        ->and(__('lunarpanel::order.action.split_order.do_not_apply'))->toBe('Do not apply')
        ->and(__('lunarpanel::order.action.split_order.move_to_order'))->toBe('Move to order:')
        ->and(__('lunarpanel::order.action.split_order.price_changed'))->toBe('The price of this product has changed since the order was placed.')
        ->and(__('lunarpanel::order.action.split_order.original_price'))->toBe('Original price')
        ->and(__('lunarpanel::order.action.split_order.current_price'))->toBe('Current price')
        ->and(__('lunarpanel::order.statuses.split'))->toBe('Splitted');

    app()->setLocale('hu');

    expect(__('lunarpanel::order.action.split_order.label'))->toBe('Rendelés bontása')
        ->and(__('lunarpanel::order.action.split_order.shipping'))->toBe('Szállítási költség')
        ->and(__('lunarpanel::order.action.split_order.coupon'))->toBe('Kupon')
        ->and(__('lunarpanel::order.action.split_order.products'))->toBe('Termékek')
        ->and(__('lunarpanel::order.action.split_order.preview'))->toBe('Előnézet')
        ->and(__('lunarpanel::order.action.split_order.do_not_apply'))->toBe('Nem alkalmazom')
        ->and(__('lunarpanel::order.action.split_order.move_to_order'))->toBe('Áthelyezés a rendelésbe:')
        ->and(__('lunarpanel::order.action.split_order.price_changed'))->toBe('A termék ára a rendelés leadása óta megváltozott.')
        ->and(__('lunarpanel::order.action.split_order.original_price'))->toBe('Eredeti ár')
        ->and(__('lunarpanel::order.action.split_order.current_price'))->toBe('Aktuális ár')
        ->and(__('lunarpanel::order.statuses.split'))->toBe('Bontva');
});

it('explains why split confirmation is unavailable', function () {
    $currency = Currency::getDefault();
    $variant = ProductVariant::factory()->create();
    ModelsPrice::factory()->create([
        'price' => 1000,
        'currency_id' => $currency->id,
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
    ]);
    $this->order->lines()->create([
        'purchasable_type' => $variant->getMorphClass(),
        'purchasable_id' => $variant->id,
        'type' => 'physical',
        'identifier' => 'only-sku',
        'description' => 'Only product',
        'quantity' => 2,
        'unit_quantity' => 1,
        'unit_price' => 1000,
        'sub_total' => 2000,
        'discount_total' => 0,
        'tax_breakdown' => new TaxBreakdown,
        'tax_total' => 0,
        'total' => 2000,
    ]);

    Livewire::test(ManageOrder::class, [
        'record' => $this->order->getRouteKey(),
    ])
        ->assertActionVisible('split_order')
        ->mountAction('split_order')
        ->assertMountedActionModalSee(__('lunarpanel::order.infolist.unit_price.label'))
        ->assertMountedActionModalDontSee(__('lunarpanel::order.infolist.discount_breakdown.discount'))
        ->assertMountedActionModalSee(__('lunarpanel::order.action.split_order.gates.too_few_targets'))
        ->assertMountedActionModalSee(__('lunarpanel::order.action.split_order.gates.shipping_required'));
});

it('fills calculated shipping on each order when a quantity is divided', function () {
    $currency = Currency::getDefault();
    $variant = ProductVariant::factory()->create();
    ModelsPrice::factory()->create([
        'price' => 1000,
        'currency_id' => $currency->id,
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
    ]);
    $this->order->lines()->create([
        'purchasable_type' => $variant->getMorphClass(),
        'purchasable_id' => $variant->id,
        'type' => 'physical',
        'identifier' => 'divided-sku',
        'description' => 'Divided product',
        'quantity' => 3,
        'unit_quantity' => 1,
        'unit_price' => 1000,
        'sub_total' => 3000,
        'discount_total' => 0,
        'tax_breakdown' => new TaxBreakdown,
        'tax_total' => 0,
        'total' => 3000,
    ]);
    $this->order->lines()->create([
        'purchasable_type' => null,
        'purchasable_id' => null,
        'type' => 'shipping',
        'identifier' => 'courier',
        'description' => 'Courier',
        'quantity' => 1,
        'unit_quantity' => 1,
        'unit_price' => 0,
        'sub_total' => 0,
        'discount_total' => 0,
        'tax_breakdown' => new TaxBreakdown,
        'tax_total' => 0,
        'total' => 0,
    ]);

    ShippingManifest::getOptionUsing(function ($cart, string $identifier) use ($variant): ShippingOption {
        return new ShippingOption(
            name: 'Courier',
            description: null,
            identifier: $identifier,
            price: new Price((int) $cart->lines->sum('quantity') * 1000, $cart->currency, 1),
            taxClass: $variant->taxClass,
        );
    });

    try {
        $page = Livewire::test(ManageOrder::class, [
            'record' => $this->order->getRouteKey(),
        ])->mountAction('split_order');

        $data = $page->get('mountedActions')[0]['data'];
        $lineKey = array_key_first($data['lines']);
        $partKeys = array_keys($data['lines'][$lineKey]['parts']);
        $orders = collect($data['orders'])->keyBy('key');

        expect((float) $orders['1']['shipping'])->toBe(30.0)
            ->and($orders['2']['shipping'])->toBeNull();

        $page->set('mountedActions.0.data.lines.'.$lineKey.'.divided', true);
        $page->set('mountedActions.0.data.lines.'.$lineKey.'.parts.'.$partKeys[0].'.quantity', 1);
        $page->set('mountedActions.0.data.lines.'.$lineKey.'.parts.'.$partKeys[1].'.quantity', 2);

        $orders = collect($page->get('mountedActions')[0]['data']['orders'])->keyBy('key');

        expect((float) $orders['1']['shipping'])->toBe(10.0)
            ->and((float) $orders['2']['shipping'])->toBe(20.0)
            ->and($page->getMountedActionModalHtml())->not->toContain(__('lunarpanel::order.action.split_order.gates.shipping_required'));

        $firstOrderKey = collect($page->get('mountedActions')[0]['data']['orders'])
            ->search(fn (array $order): bool => ($order['key'] ?? null) === '1');

        $page->set('mountedActions.0.data.orders.'.$firstOrderKey.'.shipping', '7.50');
        $page->set('mountedActions.0.data.lines.'.$lineKey.'.parts.'.$partKeys[0].'.quantity', 2);
        $page->set('mountedActions.0.data.lines.'.$lineKey.'.parts.'.$partKeys[1].'.quantity', 1);

        $orders = collect($page->get('mountedActions')[0]['data']['orders'])->keyBy('key');

        expect((float) $orders['1']['shipping'])->toBe(7.5)
            ->and((float) $orders['2']['shipping'])->toBe(10.0);
    } finally {
        ShippingManifest::getOptionUsing(fn (): null => null);
    }
});

it('updates the preview shipping line and order total when shipping changes', function () {
    $currency = Currency::getDefault();
    $variant = ProductVariant::factory()->create();
    ModelsPrice::factory()->create([
        'price' => 1000,
        'currency_id' => $currency->id,
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
    ]);
    $this->order->lines()->create([
        'purchasable_type' => $variant->getMorphClass(),
        'purchasable_id' => $variant->id,
        'type' => 'physical',
        'identifier' => 'only-sku',
        'description' => 'Only product',
        'quantity' => 2,
        'unit_quantity' => 1,
        'unit_price' => 1000,
        'sub_total' => 2000,
        'discount_total' => 0,
        'tax_breakdown' => new TaxBreakdown,
        'tax_total' => 0,
        'total' => 2000,
    ]);
    $this->order->lines()->create([
        'purchasable_type' => null,
        'purchasable_id' => null,
        'type' => 'shipping',
        'identifier' => 'courier',
        'description' => 'Courier',
        'quantity' => 1,
        'unit_quantity' => 1,
        'unit_price' => 0,
        'sub_total' => 0,
        'discount_total' => 0,
        'tax_breakdown' => new TaxBreakdown,
        'tax_total' => 0,
        'total' => 0,
    ]);

    $page = Livewire::test(ManageOrder::class, [
        'record' => $this->order->getRouteKey(),
    ])->mountAction('split_order');

    $orderKey = array_key_first($page->get('mountedActions')[0]['data']['orders']);
    $totalLabel = __('lunarpanel::order.action.split_order.order_total');

    $page->set('mountedActions.0.data.orders.'.$orderKey.'.shipping', '50');
    $higher = $page->getMountedActionModalHtml();

    $page->set('mountedActions.0.data.orders.'.$orderKey.'.shipping', '20');
    $lower = $page->getMountedActionModalHtml();

    expect($higher)->toContain('50.00 '.$currency->code)
        ->and($lower)->toContain('20.00 '.$currency->code)
        ->and($lower)->not->toContain('50.00 '.$currency->code)
        ->and(previewOrderTotal($higher, $totalLabel))->not->toBe(previewOrderTotal($lower, $totalLabel));
});

it('hides split until the order has more than one unit', function () {
    $variant = ProductVariant::factory()->create();
    $line = $this->order->lines()->create([
        'purchasable_type' => $variant->getMorphClass(),
        'purchasable_id' => $variant->id,
        'type' => 'physical',
        'identifier' => 'only-sku',
        'description' => 'Only product',
        'quantity' => 1,
        'unit_quantity' => 1,
        'unit_price' => 1000,
        'sub_total' => 1000,
        'discount_total' => 0,
        'tax_breakdown' => new TaxBreakdown,
        'tax_total' => 0,
        'total' => 1000,
    ]);

    $page = Livewire::test(ManageOrder::class, [
        'record' => $this->order->getRouteKey(),
    ])->assertActionHidden('split_order');

    $line->update(['quantity' => 2]);

    $page->call('refreshAfterProductLineEdit')
        ->assertActionVisible('split_order');

    $line->update(['quantity' => 1]);

    $page->call('refreshAfterProductLineEdit')
        ->assertActionHidden('split_order');

    $added = ProductVariant::factory()->create();
    $this->order->lines()->create([
        'purchasable_type' => $added->getMorphClass(),
        'purchasable_id' => $added->id,
        'type' => 'physical',
        'identifier' => 'added-sku',
        'description' => 'Added product',
        'quantity' => 1,
        'unit_quantity' => 1,
        'unit_price' => 1000,
        'sub_total' => 1000,
        'discount_total' => 0,
        'tax_breakdown' => new TaxBreakdown,
        'tax_total' => 0,
        'total' => 1000,
    ]);

    $page->call('refreshAfterProductLineEdit')
        ->assertActionVisible('split_order');
});

it('hides the split action on a captured order', function () {
    $this->order->transactions()->save(Transaction::factory()->make([
        'order_id' => $this->order->id,
        'driver' => 'offline',
        'type' => 'capture',
        'success' => true,
    ]));

    Livewire::test(ManageOrder::class, [
        'record' => $this->order->getRouteKey(),
    ])->assertActionHidden('split_order');
});

it('splits an eligible order from the preview', function () {
    Language::query()->update(['default' => true]);

    $currency = Currency::getDefault();
    $group = CustomerGroup::factory()->create(['default' => true]);
    $divided = ProductVariant::factory()->create();
    $whole = ProductVariant::factory()->create();

    foreach ([$divided, $whole] as $variant) {
        ModelsPrice::factory()->create([
            'price' => 1000,
            'currency_id' => $currency->id,
            'priceable_type' => $variant->getMorphClass(),
            'priceable_id' => $variant->id,
        ]);
    }

    $dividedLine = $this->order->lines()->create([
        'purchasable_type' => $divided->getMorphClass(),
        'purchasable_id' => $divided->id,
        'type' => 'physical',
        'identifier' => 'divided-sku',
        'description' => 'Divided product',
        'quantity' => 4,
        'unit_quantity' => 1,
        'unit_price' => 1000,
        'sub_total' => 4000,
        'discount_total' => 0,
        'tax_breakdown' => new TaxBreakdown,
        'tax_total' => 0,
        'total' => 4000,
    ]);
    $wholeLine = $this->order->lines()->create([
        'purchasable_type' => $whole->getMorphClass(),
        'purchasable_id' => $whole->id,
        'type' => 'physical',
        'identifier' => 'whole-sku',
        'description' => 'Whole product',
        'quantity' => 1,
        'unit_quantity' => 1,
        'unit_price' => 1000,
        'sub_total' => 1000,
        'discount_total' => 0,
        'tax_breakdown' => new TaxBreakdown,
        'tax_total' => 0,
        'total' => 1000,
    ]);
    $this->order->lines()->create([
        'purchasable_type' => null,
        'purchasable_id' => null,
        'type' => 'shipping',
        'identifier' => 'courier',
        'description' => 'Courier',
        'quantity' => 1,
        'unit_quantity' => 1,
        'unit_price' => 0,
        'sub_total' => 0,
        'discount_total' => 0,
        'tax_breakdown' => new TaxBreakdown,
        'tax_total' => 0,
        'total' => 0,
    ]);

    $discount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => 'SAVE10',
        'starts_at' => now()->subMinute(),
        'data' => [
            'fixed_value' => false,
            'percentage' => 10,
        ],
    ]);
    $discount->channels()->attach([
        $this->order->channel_id => [
            'enabled' => true,
            'starts_at' => now()->subMinute(),
        ],
    ]);
    $discount->customerGroups()->attach([
        $group->id => [
            'enabled' => true,
            'starts_at' => now()->subMinute(),
        ],
    ]);

    $this->order->update([
        'meta' => [
            'payment_option' => 'cash-on-delivery',
            'payment_type' => 'cod',
        ],
        'discount_breakdown' => collect([
            (object) [
                'discount_id' => $discount->id,
                'lines' => collect(),
                'total' => new Price(0, $currency, 1),
            ],
        ]),
    ]);

    $shipping = number_format(1250 / (int) $currency->factor, $currency->decimal_places, '.', '');

    Livewire::test(ManageOrder::class, [
        'record' => $this->order->getRouteKey(),
    ])
        ->assertActionVisible('split_order')
        ->mountAction('split_order')
        ->assertMountedActionModalSee(__('lunarpanel::order.infolist.unit_price.label'))
        ->assertMountedActionModalSee(__('lunarpanel::order.infolist.discount_breakdown.discount'));

    Livewire::test(ManageOrder::class, [
        'record' => $this->order->getRouteKey(),
    ])->callAction('split_order', [
        'orders' => [
            ['key' => '1', 'shipping' => $shipping, 'payment_option' => 'cash-on-delivery'],
            ['key' => '2', 'shipping' => '0.00', 'payment_option' => 'cash-on-delivery'],
        ],
        'lines' => [
            [
                'order_line_id' => $dividedLine->id,
                'quantity' => 4,
                'description' => 'Divided product',
                'divided' => true,
                'target' => '1',
                'parts' => [
                    ['target' => '1', 'quantity' => 1],
                    ['target' => '2', 'quantity' => 3],
                ],
            ],
            [
                'order_line_id' => $wholeLine->id,
                'quantity' => 1,
                'description' => 'Whole product',
                'divided' => false,
                'target' => '2',
                'parts' => [],
            ],
        ],
        'coupon' => '1',
    ]);

    $children = Order::query()->where('id', '!=', $this->order->id)->get();
    $withCoupon = $children->first(fn (Order $order) => $order->productLines->contains(
        fn ($line) => (int) $line->purchasable_id === $divided->id && (int) $line->quantity === 1
    ));

    expect($this->order->fresh()->status)->toBe('split')
        ->and($withCoupon)->not->toBeNull()
        ->and($withCoupon->shipping_total->value)->toBe(1250)
        ->and($withCoupon->discount_breakdown->pluck('discount_id')->all())->toContain($discount->id)
        ->and($withCoupon->shippingLines()->first()->identifier)->toBe('courier');
});

it('hides edits on a split order and links the related orders', function () {
    $currency = Currency::getDefault();
    $variant = ProductVariant::factory()->create();
    $line = $this->order->lines()->create([
        'purchasable_type' => $variant->getMorphClass(),
        'purchasable_id' => $variant->id,
        'type' => 'physical',
        'identifier' => 'kept-sku',
        'description' => 'Kept product',
        'quantity' => 1,
        'unit_quantity' => 1,
        'unit_price' => 1000,
        'sub_total' => 1000,
        'discount_total' => 0,
        'tax_breakdown' => new TaxBreakdown,
        'tax_total' => 0,
        'total' => 1000,
    ]);
    $child = Order::factory()->create([
        'currency_code' => $currency->code,
        'reference' => 'CHILD-REF',
        'status' => 'payment-offline',
        'meta' => [
            'split_from_order_id' => $this->order->id,
        ],
    ]);

    $this->order->update([
        'status' => 'split',
        'reference' => 'PARENT-REF',
        'meta' => [
            'split_into_order_ids' => [$child->id],
        ],
    ]);
    $this->order->transactions()->save(Transaction::factory()->make([
        'order_id' => $this->order->id,
        'driver' => 'offline',
        'type' => 'intent',
        'success' => true,
    ]));

    $parentPage = Livewire::test(ManageOrder::class, [
        'record' => $this->order->getRouteKey(),
    ])
        ->assertActionHidden('split_order')
        ->assertActionHidden('update_status')
        ->assertActionHidden('capture')
        ->assertSee('CHILD-REF')
        ->assertDontSee('split_into_order_ids');

    expect(alignedOrderLink($parentPage->html(), 'CHILD-REF'))->toBeTrue();

    Livewire::test(OrderItemsTable::class, [
        'record' => $this->order->fresh(),
    ])
        ->assertTableActionHidden('add_product')
        ->assertTableActionHidden('update_quantity', $line)
        ->assertTableActionHidden('remove_product', $line);

    $childPage = Livewire::test(ManageOrder::class, [
        'record' => $child->getRouteKey(),
    ])
        ->assertSee('PARENT-REF')
        ->assertDontSee('split_from_order_id');

    expect(alignedOrderLink($childPage->html(), 'PARENT-REF'))->toBeTrue();
});

function previewOrderTotal(string $html, string $label): ?string
{
    return previewEntryAmounts($html, $label)[0] ?? null;
}

/**
 * @return list<string>
 */
function previewEntryAmounts(string $html, string $label): array
{
    $document = new DOMDocument;
    $document->loadHTML($html, LIBXML_NOERROR);
    $xpath = new DOMXPath($document);
    $labels = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " fi-in-entry-label ")]');
    $amounts = [];

    if ($labels === false) {
        return [];
    }

    foreach ($labels as $node) {
        if (! $node instanceof DOMElement || trim($node->textContent) !== $label) {
            continue;
        }

        $entry = $node->parentNode;

        while ($entry instanceof DOMElement && ! in_array('fi-in-entry', preg_split('/\s+/', $entry->getAttribute('class')) ?: [], true)) {
            $entry = $entry->parentNode;
        }

        if (! $entry instanceof DOMElement) {
            continue;
        }

        $content = $xpath->query('.//*[contains(@class, "fi-in-entry-content")]', $entry);
        $amount = $content !== false ? $content->item(0) : null;

        if ($amount instanceof DOMNode) {
            $amounts[] = trim($amount->textContent);
        }
    }

    return $amounts;
}

function alignedOrderLink(string $html, string $reference): bool
{
    $document = new DOMDocument;
    $document->loadHTML($html, LIBXML_NOERROR);
    $links = (new DOMXPath($document))->query('//*[contains(@class, "fi-align-end")]//a[contains(text(), "'.$reference.'")]');

    return $links !== false && $links->length > 0;
}

it('keeps the coupon on the order named in the coupon select', function () {
    $currency = Currency::getDefault();
    $variants = ProductVariant::factory(3)->create();

    foreach ($variants as $index => $variant) {
        $this->order->lines()->create([
            'purchasable_type' => $variant->getMorphClass(),
            'purchasable_id' => $variant->id,
            'type' => 'physical',
            'identifier' => 'sku-'.$index,
            'description' => 'Product '.$index,
            'quantity' => 1,
            'unit_quantity' => 1,
            'unit_price' => 1000,
            'sub_total' => 1000,
            'discount_total' => 0,
            'tax_breakdown' => new TaxBreakdown,
            'tax_total' => 0,
            'total' => 1000,
        ]);
    }

    $discount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => 'SAVE10',
        'starts_at' => now()->subMinute(),
        'data' => [
            'fixed_value' => false,
            'percentage' => 10,
        ],
    ]);

    $this->order->update([
        'discount_breakdown' => collect([
            (object) [
                'discount_id' => $discount->id,
                'lines' => collect(),
                'total' => new Price(0, $currency, 1),
            ],
        ]),
    ]);

    $component = Livewire::test(ManageOrder::class, [
        'record' => $this->order->fresh()->getRouteKey(),
    ])->mountAction('split_order');

    $data = $component->get('mountedActions')[0]['data'];
    $lineKeys = array_keys($data['lines']);
    $component->set('mountedActions.0.data.lines.'.$lineKeys[2].'.target', '2');

    $method = new ReflectionMethod($component->instance(), 'getMountedActionForm');
    $form = $method->invoke($component->instance());
    $options = $form->getComponent('coupon')->getOptions();
    $newOrder = __('lunarpanel::order.action.split_order.new_order');

    expect($options[1])->toBe($newOrder.' 1')
        ->and($options[2])->toBe($newOrder.' 2')
        ->and(array_key_exists(0, $options))->toBeFalse();

    $component->set('mountedActions.0.data.coupon', array_search($newOrder.' 1', $options, true))
        ->assertMountedActionModalDontSee(__('lunarpanel::order.action.split_order.gates.coupon'));
});

it('starts a mixed order with preorders on one order and the other products on the other', function () {
    $preorder = ProductVariant::factory()->create(['purchasable' => 'preorder']);
    $firstSimple = ProductVariant::factory()->create(['purchasable' => 'in_stock']);
    $secondSimple = ProductVariant::factory()->create(['purchasable' => 'always']);

    foreach ([$firstSimple, $preorder, $secondSimple] as $index => $variant) {
        $this->order->lines()->create([
            'purchasable_type' => $variant->getMorphClass(),
            'purchasable_id' => $variant->id,
            'type' => 'physical',
            'identifier' => 'sku-'.$index,
            'description' => 'Product '.$index,
            'quantity' => 1,
            'unit_quantity' => 1,
            'unit_price' => 1000,
            'sub_total' => 1000,
            'discount_total' => 0,
            'tax_breakdown' => new TaxBreakdown,
            'tax_total' => 0,
            'total' => 1000,
        ]);
    }

    $ordersBefore = Order::query()->count();

    $lines = collect(Livewire::test(ManageOrder::class, [
        'record' => $this->order->getRouteKey(),
    ])->mountAction('split_order')->get('mountedActions')[0]['data']['lines'])->values();

    expect(Order::query()->count())->toBe($ordersBefore)
        ->and($this->order->fresh()->status)->not->toBe('split')
        ->and($lines->pluck('target')->all())->toBe(['2', '1', '2']);
});

it('offers the stored unit price when a split quantity loses its bulk price', function () {
    Language::query()->update(['default' => true]);

    $currency = Currency::getDefault();
    CustomerGroup::factory()->create(['default' => true]);
    $variant = ProductVariant::factory()->create();

    ModelsPrice::factory()->create([
        'price' => 1000,
        'min_quantity' => 1,
        'currency_id' => $currency->id,
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
    ]);
    ModelsPrice::factory()->create([
        'price' => 800,
        'min_quantity' => 5,
        'currency_id' => $currency->id,
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
    ]);

    $this->order->lines()->create([
        'purchasable_type' => $variant->getMorphClass(),
        'purchasable_id' => $variant->id,
        'type' => 'physical',
        'identifier' => 'bulk-sku',
        'description' => 'Bulk product',
        'quantity' => 6,
        'unit_quantity' => 1,
        'unit_price' => 800,
        'sub_total' => 4800,
        'discount_total' => 0,
        'tax_breakdown' => new TaxBreakdown,
        'tax_total' => 0,
        'total' => 4800,
    ]);

    $original = __('lunarpanel::order.action.split_order.original_price');
    $current = __('lunarpanel::order.action.split_order.current_price');
    $changed = __('lunarpanel::order.action.split_order.price_changed');

    $component = Livewire::test(ManageOrder::class, [
        'record' => $this->order->fresh()->getRouteKey(),
    ])->mountAction('split_order')
        ->assertMountedActionModalDontSee($original)
        ->assertMountedActionModalDontSee($current)
        ->assertMountedActionModalDontSee($changed);

    $data = $component->get('mountedActions')[0]['data'];
    $lineKey = array_key_first($data['lines']);
    $partKeys = array_keys($data['lines'][$lineKey]['parts']);

    $component->set('mountedActions.0.data.lines.'.$lineKey.'.divided', true)
        ->set('mountedActions.0.data.lines.'.$lineKey.'.parts.'.$partKeys[0].'.target', '1')
        ->set('mountedActions.0.data.lines.'.$lineKey.'.parts.'.$partKeys[0].'.quantity', 2)
        ->set('mountedActions.0.data.lines.'.$lineKey.'.parts.'.$partKeys[1].'.target', '2')
        ->set('mountedActions.0.data.lines.'.$lineKey.'.parts.'.$partKeys[1].'.quantity', 4)
        ->assertMountedActionModalSee($original)
        ->assertMountedActionModalSee($current)
        ->assertMountedActionModalSee($changed);

    $unitPrice = __('lunarpanel::order.infolist.unit_price.label');
    $originalAmounts = implode(' ', previewEntryAmounts($component->getMountedActionModalHtml(), $unitPrice));

    expect($originalAmounts)->toContain('8.00')
        ->and($originalAmounts)->not->toContain('10.00');

    $component->set('mountedActions.0.data.lines.'.$lineKey.'.price_source', 'current');

    $currentAmounts = implode(' ', previewEntryAmounts($component->getMountedActionModalHtml(), $unitPrice));

    expect($currentAmounts)->toContain('10.00')
        ->and($currentAmounts)->not->toContain('8.00');
});
