<?php

use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Lunar\Admin\Filament\Resources\OrderResource\Pages\Components\OrderItemsTable;
use Lunar\Admin\Filament\Resources\OrderResource\Pages\ManageOrder;
use Lunar\Facades\Taxes;
use Lunar\Models\Cart;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;
use Lunar\Models\Price;
use Lunar\Models\ProductVariant;
use Lunar\Models\Transaction;
use Lunar\Tests\Admin\Feature\Filament\TestCase;
use Lunar\Tests\Core\Stubs\TestTaxDriver;

uses(TestCase::class)
    ->group('resource.order');

beforeEach(function () {
    $this->asStaff();

    Config::set('lunar.taxes.driver', 'test');
    Taxes::extend('test', fn ($app) => $app->make(TestTaxDriver::class));

    Language::factory()->create([
        'code' => 'en',
        'default' => true,
    ]);

    $this->currency = Currency::factory()->create([
        'default' => true,
        'decimal_places' => 2,
    ]);

    $this->channel = Channel::factory()->create([
        'default' => true,
    ]);
});

/**
 * @return array{order: Order, line: OrderLine, variant: ProductVariant}
 */
function editableOrderLine(int $quantity = 1, int $price = 2500): array
{
    $variant = ProductVariant::factory()->create();

    Price::factory()->create([
        'price' => $price,
        'currency_id' => test()->currency->id,
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
    ]);

    $cart = Cart::factory()->create([
        'currency_id' => test()->currency->id,
        'channel_id' => test()->channel->id,
    ]);

    $order = Order::factory()->create([
        'channel_id' => test()->channel->id,
        'currency_code' => test()->currency->code,
        'cart_id' => $cart->id,
        'status' => 'awaiting-payment',
        'meta' => ['payment_reference' => 'pay-1'],
    ]);

    $line = OrderLine::factory()->create([
        'order_id' => $order->id,
        'purchasable_type' => $variant->getMorphClass(),
        'purchasable_id' => $variant->id,
        'type' => 'physical',
        'description' => 'Kept product',
        'quantity' => $quantity,
        'unit_price' => $price,
        'unit_quantity' => 1,
        'sub_total' => $price * $quantity,
        'discount_total' => 0,
        'tax_total' => 0,
        'total' => $price * $quantity,
    ]);

    return compact('order', 'line', 'variant');
}

it('saves an added variant, a new quantity, and a removed line and shows the new total', function () {
    ['order' => $order, 'line' => $line] = editableOrderLine();
    $added = ProductVariant::factory()->create();

    Price::factory()->create([
        'price' => 1000,
        'currency_id' => $this->currency->id,
        'priceable_type' => $added->getMorphClass(),
        'priceable_id' => $added->id,
    ]);

    Livewire::test(OrderItemsTable::class, [
        'record' => $order,
    ])
        ->assertTableActionVisible('add_product')
        ->assertTableActionVisible('update_quantity', $line)
        ->assertTableActionVisible('remove_product', $line)
        ->callTableAction('update_quantity', $line, data: [
            'quantity' => 2,
        ])
        ->callTableAction('add_product', data: [
            'product_id' => $added->product_id,
            'quantity' => 1,
        ]);

    $addedLine = $order->fresh()->productLines()->where('purchasable_id', $added->id)->first();

    Livewire::test(OrderItemsTable::class, [
        'record' => $order->fresh(),
    ])->callTableAction('remove_product', $addedLine);

    $order->refresh();
    $calculated = $order->cart->recalculate();

    expect($order->productLines)->toHaveCount(1)
        ->and($order->productLines->first()->quantity)->toBe(2)
        ->and($order->total->value)->toBe($calculated->total->value);

    Livewire::test(ManageOrder::class, [
        'record' => $order->getRouteKey(),
    ])
        ->assertSee($order->total->formatted)
        ->assertDontSee(__('lunarpanel::order.infolist.alert.product_line_gates.paid'));
});

it('offers only the selected product variants in the add product dropdown', function () {
    ['order' => $order] = editableOrderLine();

    $first = ProductVariant::factory()->create(['sku' => 'RED-SKU']);
    $second = ProductVariant::factory()->create([
        'product_id' => $first->product_id,
        'sku' => 'BLUE-SKU',
    ]);
    $other = ProductVariant::factory()->create(['sku' => 'OTHER-SKU']);

    Price::factory()->create([
        'price' => 1000,
        'currency_id' => $this->currency->id,
        'priceable_type' => $second->getMorphClass(),
        'priceable_id' => $second->id,
    ]);

    Livewire::test(OrderItemsTable::class, [
        'record' => $order,
    ])
        ->mountTableAction('add_product')
        ->assertFormFieldDoesNotExist('variant_id')
        ->setTableActionData([
            'product_id' => $first->product_id,
        ])
        ->assertFormFieldExists('variant_id', function (Select $field) use ($first, $second): bool {
            $labels = $field->getOptions();

            expect(array_map(strval(...), array_keys($labels)))->toEqualCanonicalizing([
                (string) $first->getKey(),
                (string) $second->getKey(),
            ])
                ->and(implode(' ', $labels))->toContain('RED-SKU', 'BLUE-SKU');

            return true;
        })
        ->setTableActionData([
            'product_id' => $other->product_id,
        ])
        ->assertFormFieldDoesNotExist('variant_id')
        ->setTableActionData([
            'product_id' => $first->product_id,
            'variant_id' => $other->getKey(),
            'quantity' => 1,
        ])
        ->callMountedTableAction()
        ->assertHasFormErrors(['variant_id']);

    expect($order->fresh()->productLines()->whereIn('purchasable_id', [$first->getKey(), $second->getKey(), $other->getKey()])->exists())->toBeFalse();

    Livewire::test(OrderItemsTable::class, [
        'record' => $order->fresh(),
    ])->callTableAction('add_product', data: [
        'product_id' => $first->product_id,
        'variant_id' => $second->getKey(),
        'quantity' => 2,
    ]);

    $lines = $order->fresh()->productLines;

    expect($lines->firstWhere('purchasable_id', $second->getKey())?->quantity)->toBe(2)
        ->and($lines->firstWhere('purchasable_id', $first->getKey()))->toBeNull();
});

it('shows a toast when the only product line cannot be removed', function () {
    ['order' => $order, 'line' => $line] = editableOrderLine();

    Livewire::test(OrderItemsTable::class, [
        'record' => $order,
    ])
        ->callTableAction('remove_product', $line)
        ->assertNotified(__('lunarpanel::order.action.product_line_edit.last_line'));

    expect($order->fresh()->productLines()->whereKey($line->id)->exists())->toBeTrue();
});

it('hides product line actions when the order is locked', function () {
    ['order' => $order, 'line' => $line] = editableOrderLine();

    $order->transactions()->save(Transaction::factory()->make([
        'type' => 'capture',
        'success' => true,
    ]));

    Livewire::test(OrderItemsTable::class, [
        'record' => $order->fresh(),
    ])
        ->assertTableActionHidden('add_product')
        ->assertTableActionHidden('update_quantity', $line)
        ->assertTableActionHidden('remove_product', $line);
});
