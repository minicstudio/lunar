<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Models\Cart;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Lunar\Models\OrderLine;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Lunar\Shipping\Models\ShippingMethod;
use Lunar\Shipping\Models\ShippingRate;
use Lunar\Shipping\Models\ShippingZone;
use Lunar\Shipping\PreorderConsultationShipping;
use Lunar\Tests\Shipping\TestCase;

uses(TestCase::class)->group('shipping');

uses(RefreshDatabase::class);

test('the consultation quote prices every product line by weight', function () {
    $currency = Currency::factory()->create([
        'default' => true,
        'decimal_places' => 2,
    ]);

    $country = Country::factory()->create();

    TaxClass::factory()->create([
        'default' => true,
    ]);

    $customerGroup = CustomerGroup::factory()->create([
        'default' => true,
    ]);

    $zone = ShippingZone::factory()->create([
        'type' => 'countries',
    ]);
    $zone->countries()->attach($country);

    $method = ShippingMethod::factory()->create([
        'driver' => 'ship-by',
        'code' => 'dpd',
        'data' => [
            'charge_by' => 'weight',
        ],
    ]);
    $method->customerGroups()->sync([
        $customerGroup->id => [
            'enabled' => true,
            'visible' => true,
            'starts_at' => now(),
            'ends_at' => null,
        ],
    ]);

    $rate = ShippingRate::factory()->create([
        'shipping_method_id' => $method->id,
        'shipping_zone_id' => $zone->id,
        'enabled' => true,
    ]);
    $rate->prices()->createMany([
        ['price' => 1000, 'min_quantity' => 1, 'currency_id' => $currency->id],
        ['price' => 2500, 'min_quantity' => 10, 'currency_id' => $currency->id],
    ]);

    $simple = ProductVariant::factory()->create([
        'weight_value' => 3,
        'weight_unit' => 'kg',
    ]);
    $preorder = ProductVariant::factory()->create([
        'weight_value' => 8,
        'weight_unit' => 'kg',
        'purchasable' => 'preorder',
    ]);

    $order = Order::factory()->create([
        'currency_code' => $currency->code,
        'shipping_total' => 1000,
        'meta' => ['shippingType' => 'courier'],
    ]);

    OrderAddress::factory()->create([
        'order_id' => $order->id,
        'type' => 'shipping',
        'country_id' => $country->id,
        'postcode' => '1000',
        'state' => null,
    ]);

    OrderLine::factory()->create([
        'order_id' => $order->id,
        'purchasable_type' => $simple->getMorphClass(),
        'purchasable_id' => $simple->id,
        'type' => 'physical',
        'quantity' => 1,
    ]);
    OrderLine::factory()->create([
        'order_id' => $order->id,
        'purchasable_type' => $preorder->getMorphClass(),
        'purchasable_id' => $preorder->id,
        'type' => 'physical',
        'quantity' => 1,
        'meta' => ['preorder' => true],
    ]);
    OrderLine::factory()->create([
        'order_id' => $order->id,
        'type' => 'shipping',
        'identifier' => 'dpd',
        'description' => 'DPD',
        'quantity' => 1,
        'unit_price' => 1000,
        'sub_total' => 1000,
        'total' => 1000,
    ]);

    $preorderLine = $order->productLines()->where('purchasable_id', $preorder->id)->first();

    expect(PreorderConsultationShipping::suggestedMinor($order->fresh()))->toBe(2500)
        ->and(PreorderConsultationShipping::suggestedMinor($order->fresh(), [$preorderLine->id]))->toBe(1000)
        ->and(Cart::query()->count())->toBe(0);
});
