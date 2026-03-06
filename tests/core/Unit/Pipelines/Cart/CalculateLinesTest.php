<?php

uses(\Lunar\Tests\Core\TestCase::class);

use Illuminate\Support\Facades\Config;
use Lunar\DataTypes\Price as DataTypesPrice;
use Lunar\Models\Cart;
use Lunar\Models\Currency;
use Lunar\Models\Price;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Lunar\Models\TaxRate;
use Lunar\Models\TaxRateAmount;
use Lunar\Models\TaxZone;
use Lunar\Pipelines\Cart\CalculateLines;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('can calculate lines', function ($expectedUnitPrice, $incomingUnitPrice, $unitQuantity) {
    $currency = Currency::factory()->create();

    $cart = Cart::factory()->create([
        'currency_id' => $currency->id,
    ]);

    $purchasable = ProductVariant::factory()->create([
        'unit_quantity' => $unitQuantity,
    ]);

    Price::factory()->create([
        'price' => $incomingUnitPrice,
        'min_quantity' => 1,
        'currency_id' => $currency->id,
        'priceable_type' => $purchasable->getMorphClass(),
        'priceable_id' => $purchasable->id,
    ]);

    $cart->lines()->create([
        'purchasable_type' => $purchasable->getMorphClass(),
        'purchasable_id' => $purchasable->id,
        'quantity' => 1,
    ]);

    $cart = app(CalculateLines::class)->handle($cart, function ($cart) {
        return $cart;
    });

    $cartLine = $cart->lines->first();

    expect($expectedUnitPrice)->toEqual($cartLine->subTotal->unitDecimal);
})->with('providePurchasableData');

dataset('providePurchasableData', function () {
    return [
        'purchasable with 1 unit quantity' => [
            '1.00',
            '100',
            '1',
        ],
        'purchasable with 10 unit quantity' => [
            '0.10',
            '100',
            '10',
        ],
        'purchasable with 100 unit quantity' => [
            '0.01',
            '100',
            '100',
        ],
        'another purchasable with 100 unit quantity' => [
            '0.55',
            '5503',
            '100',
        ],
    ];
});

function createLineTaxClassWithZone(int $percentage = 20): TaxClass
{
    $taxZone = TaxZone::factory()->create(['default' => true]);
    $taxRate = TaxRate::factory()->create(['tax_zone_id' => $taxZone->id]);
    $taxClass = TaxClass::factory()->create();

    TaxRateAmount::factory()->create([
        'percentage' => $percentage,
        'tax_class_id' => $taxClass->id,
        'tax_rate_id' => $taxRate->id,
    ]);

    return $taxClass;
}

test('sets default discount values on cart lines', function () {
    Config::set('lunar.pricing.stored_inclusive_of_tax', false);

    $currency = Currency::factory()->create();

    $cart = Cart::factory()->create([
        'currency_id' => $currency->id,
    ]);

    $taxClass = createLineTaxClassWithZone(20);

    $purchasable = ProductVariant::factory()->create([
        'tax_class_id' => $taxClass->id,
    ]);

    Price::factory()->create([
        'price' => 1000,
        'min_quantity' => 1,
        'currency_id' => $currency->id,
        'priceable_type' => $purchasable->getMorphClass(),
        'priceable_id' => $purchasable->id,
    ]);

    $cart->lines()->create([
        'purchasable_type' => $purchasable->getMorphClass(),
        'purchasable_id' => $purchasable->id,
        'quantity' => 2,
    ]);

    $result = app(CalculateLines::class)->handle($cart, fn($cart) => $cart);

    $line = $result->lines->first();

    expect($line->unitPriceWithoutCoupon)->toBeInstanceOf(DataTypesPrice::class)
        ->and($line->unitPriceWithoutCouponIncTax)->toBeInstanceOf(DataTypesPrice::class)
        ->and($line->discountTotalWithoutCoupon)->toBeInstanceOf(DataTypesPrice::class)
        ->and($line->discountTotalWithoutCoupon->value)->toBe(0)
        ->and($line->discountTotalWithoutCouponIncTax)->toBeInstanceOf(DataTypesPrice::class)
        ->and($line->discountTotalWithoutCouponIncTax->value)->toBe(0)
        ->and($line->subTotalDiscountedWithoutCouponIncTax)->toBeInstanceOf(DataTypesPrice::class)
        ->and($line->subTotalDiscountedWithoutCouponIncTax->value)->toBeGreaterThan($line->subTotalDiscounted->value);
});

test('returns original prices when pricing is inclusive of tax', function () {
    Config::set('lunar.pricing.stored_inclusive_of_tax', true);

    $currency = Currency::factory()->create();

    $cart = Cart::factory()->create([
        'currency_id' => $currency->id,
    ]);

    $taxClass = createLineTaxClassWithZone(20);

    $purchasable = ProductVariant::factory()->create([
        'tax_class_id' => $taxClass->id,
    ]);

    Price::factory()->create([
        'price' => 1000,
        'min_quantity' => 1,
        'currency_id' => $currency->id,
        'priceable_type' => $purchasable->getMorphClass(),
        'priceable_id' => $purchasable->id,
    ]);

    $cart->lines()->create([
        'purchasable_type' => $purchasable->getMorphClass(),
        'purchasable_id' => $purchasable->id,
        'quantity' => 1,
    ]);

    $result = app(CalculateLines::class)->handle($cart, fn($cart) => $cart);

    $line = $result->lines->first();

    expect($line->unitPriceWithoutCoupon->value)->toBe($line->unitPriceWithoutCouponIncTax->value)
        ->and($line->subTotalDiscountedWithoutCouponIncTax->value)->toBe($line->subTotalDiscounted->value);
});

test('uses zero tax rate when purchasable has no tax rate', function () {
    Config::set('lunar.pricing.stored_inclusive_of_tax', false);

    $currency = Currency::factory()->create();

    $cart = Cart::factory()->create([
        'currency_id' => $currency->id,
    ]);

    $taxClass = TaxClass::factory()->create();

    $purchasable = ProductVariant::factory()->create([
        'tax_class_id' => $taxClass->id,
    ]);

    Price::factory()->create([
        'price' => 1000,
        'min_quantity' => 1,
        'currency_id' => $currency->id,
        'priceable_type' => $purchasable->getMorphClass(),
        'priceable_id' => $purchasable->id,
    ]);

    $cart->lines()->create([
        'purchasable_type' => $purchasable->getMorphClass(),
        'purchasable_id' => $purchasable->id,
        'quantity' => 1,
    ]);

    $result = app(CalculateLines::class)->handle($cart, fn($cart) => $cart);

    $line = $result->lines->first();

    expect($line->unitPriceWithoutCoupon->value)->toBe($line->unitPriceWithoutCouponIncTax->value);
});

test('addTax returns original price when pricing is inclusive', function () {
    Config::set('lunar.pricing.stored_inclusive_of_tax', true);

    $reflection = new ReflectionClass(app(CalculateLines::class));
    $method = $reflection->getMethod('addTax');

    $currency = Currency::factory()->create();
    $price = new DataTypesPrice(1000, $currency, 1);

    $result = $method->invoke(app(CalculateLines::class), $price, 0.20);

    expect($result->value)->toBe(1000);
});

test('addTax adds tax when pricing is not inclusive', function () {
    Config::set('lunar.pricing.stored_inclusive_of_tax', false);

    $reflection = new ReflectionClass(app(CalculateLines::class));
    $method = $reflection->getMethod('addTax');

    $currency = Currency::factory()->create();
    $price = new DataTypesPrice(1000, $currency, 1);

    $result = $method->invoke(app(CalculateLines::class), $price, 0.20);

    expect($result->value)->toBe(1200);
});

test('processes multiple cart lines correctly', function () {
    Config::set('lunar.pricing.stored_inclusive_of_tax', false);

    $currency = Currency::factory()->create();

    $cart = Cart::factory()->create([
        'currency_id' => $currency->id,
    ]);

    $taxClass1 = createLineTaxClassWithZone(20);
    $taxClass2 = createLineTaxClassWithZone(10);

    $purchasable1 = ProductVariant::factory()->create([
        'tax_class_id' => $taxClass1->id,
    ]);

    $purchasable2 = ProductVariant::factory()->create([
        'tax_class_id' => $taxClass2->id,
    ]);

    Price::factory()->create([
        'price' => 1000,
        'min_quantity' => 1,
        'currency_id' => $currency->id,
        'priceable_type' => $purchasable1->getMorphClass(),
        'priceable_id' => $purchasable1->id,
    ]);

    Price::factory()->create([
        'price' => 2000,
        'min_quantity' => 1,
        'currency_id' => $currency->id,
        'priceable_type' => $purchasable2->getMorphClass(),
        'priceable_id' => $purchasable2->id,
    ]);

    $cart->lines()->create([
        'purchasable_type' => $purchasable1->getMorphClass(),
        'purchasable_id' => $purchasable1->id,
        'quantity' => 1,
    ]);

    $cart->lines()->create([
        'purchasable_type' => $purchasable2->getMorphClass(),
        'purchasable_id' => $purchasable2->id,
        'quantity' => 1,
    ]);

    $result = app(CalculateLines::class)->handle($cart, fn($cart) => $cart);

    expect($result->lines)->toHaveCount(2);

    foreach ($result->lines as $line) {
        expect($line->unitPriceWithoutCoupon)->toBeInstanceOf(DataTypesPrice::class)
            ->and($line->unitPriceWithoutCouponIncTax)->toBeInstanceOf(DataTypesPrice::class)
            ->and($line->discountTotalWithoutCoupon)->toBeInstanceOf(DataTypesPrice::class)
            ->and($line->discountTotalWithoutCouponIncTax)->toBeInstanceOf(DataTypesPrice::class)
            ->and($line->subTotalDiscountedWithoutCouponIncTax)->toBeInstanceOf(DataTypesPrice::class);
    }
});
