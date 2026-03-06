<?php

uses(\Lunar\Tests\Core\TestCase::class);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Lunar\DataTypes\Price as DataTypesPrice;
use Lunar\Models\Cart;
use Lunar\Models\Currency;
use Lunar\Models\Discount;
use Lunar\Models\Price;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Lunar\Models\TaxRate;
use Lunar\Models\TaxRateAmount;
use Lunar\Models\TaxZone;
use Lunar\Pipelines\Cart\Calculate;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function createCalculateTaxClassWithZone(int $percentage = 20): TaxClass
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

function createCalculateCartWithLine(int $price, int $quantity = 1, int $taxRate = 20): array
{
    $currency = Currency::factory()->create();

    $cart = Cart::factory()->create([
        'currency_id' => $currency->id,
    ]);

    $taxClass = createCalculateTaxClassWithZone($taxRate);

    $purchasable = ProductVariant::factory()->create([
        'tax_class_id' => $taxClass->id,
    ]);

    Price::factory()->create([
        'price' => $price,
        'min_quantity' => 1,
        'currency_id' => $currency->id,
        'priceable_type' => $purchasable->getMorphClass(),
        'priceable_id' => $purchasable->id,
    ]);

    $cart->lines()->create([
        'purchasable_type' => $purchasable->getMorphClass(),
        'purchasable_id' => $purchasable->id,
        'quantity' => $quantity,
    ]);

    $cart->load('lines.purchasable.taxClass.taxRateAmounts.taxRate');

    return [$cart, $currency];
}

function runCalculatePipeline(Cart $cart): Cart
{
    return app(Calculate::class)->handle($cart, fn($cart) => $cart);
}

test('handle calculates cart totals without discounts', function () {
    [$cart, $currency] = createCalculateCartWithLine(1000, 2, 20);

    $line = $cart->lines->first();
    $line->subTotal = new DataTypesPrice(2000, $currency, 1);
    $line->subTotalDiscounted = new DataTypesPrice(2000, $currency, 1);
    $line->subTotalDiscountedWithoutCouponIncTax = new DataTypesPrice(2400, $currency, 1);
    $line->discountTotal = new DataTypesPrice(0, $currency, 1);
    $line->discountTotalWithoutCoupon = new DataTypesPrice(0, $currency, 1);
    $line->discountTotalWithoutCouponIncTax = new DataTypesPrice(0, $currency, 1);

    $cart->discountBreakdown = new Collection;
    $cart->shippingTotal = new DataTypesPrice(0, $currency, 1);

    $result = runCalculatePipeline($cart);

    expect($result->couponTotal->value)->toBe(0)
        ->and($result->discountTotalWithoutCoupon->value)->toBe(0)
        ->and($result->subTotalDiscountedWithoutCouponIncTax->value)->toBe(2400)
        ->and($result->couponTotalIncTax->value)->toBe(0)
        ->and($result->discountTotalWithoutCouponIncTax->value)->toBe(0)
        ->and($result->total->value)->toBe(2400);
});

test('handle calculates cart totals with coupon discount', function () {
    [$cart, $currency] = createCalculateCartWithLine(1000, 1, 20);

    $couponDiscount = Discount::factory()->create([
        'coupon' => 'CARTCOUPON',
    ]);

    $line = $cart->lines->first();
    $line->subTotal = new DataTypesPrice(1000, $currency, 1);
    $line->subTotalDiscounted = new DataTypesPrice(900, $currency, 1);
    $line->subTotalDiscountedWithoutCouponIncTax = new DataTypesPrice(1200, $currency, 1);
    $line->discountTotal = new DataTypesPrice(100, $currency, 1);
    $line->discountTotalWithoutCoupon = new DataTypesPrice(0, $currency, 1);
    $line->discountTotalWithoutCouponIncTax = new DataTypesPrice(0, $currency, 1);

    $cart->discountBreakdown = collect([
        (object) [
            'discount' => $couponDiscount,
            'price' => new DataTypesPrice(100, $currency, 1),
        ],
    ]);
    $cart->shippingTotal = new DataTypesPrice(0, $currency, 1);

    $result = runCalculatePipeline($cart);

    expect($result->couponTotal->value)->toBe(100)
        ->and($result->couponTotalIncTax->value)->toBe(120)
        ->and($result->discountTotalWithoutCoupon->value)->toBe(0)
        ->and($result->discountTotalWithoutCouponIncTax->value)->toBe(0)
        ->and($result->subTotalDiscountedWithoutCouponIncTax->value)->toBe(1200)
        ->and($result->total->value)->toBe(1080);
});

test('handle calculates cart totals with non coupon discount', function () {
    [$cart, $currency] = createCalculateCartWithLine(1000, 2, 20);

    $discount = Discount::factory()->create([
        'coupon' => null,
    ]);

    $line = $cart->lines->first();
    $line->subTotal = new DataTypesPrice(2000, $currency, 1);
    $line->subTotalDiscounted = new DataTypesPrice(1700, $currency, 1);
    $line->subTotalDiscountedWithoutCouponIncTax = new DataTypesPrice(2040, $currency, 1);
    $line->discountTotal = new DataTypesPrice(300, $currency, 1);
    $line->discountTotalWithoutCoupon = new DataTypesPrice(300, $currency, 1);
    $line->discountTotalWithoutCouponIncTax = new DataTypesPrice(360, $currency, 1);

    $cart->discountBreakdown = collect([
        (object) [
            'discount' => $discount,
            'price' => new DataTypesPrice(300, $currency, 1),
        ],
    ]);
    $cart->shippingTotal = new DataTypesPrice(0, $currency, 1);

    $result = runCalculatePipeline($cart);

    expect($result->couponTotal->value)->toBe(0)
        ->and($result->couponTotalIncTax->value)->toBe(0)
        ->and($result->discountTotalWithoutCoupon->value)->toBe(300)
        ->and($result->discountTotalWithoutCouponIncTax->value)->toBe(360)
        ->and($result->subTotalDiscountedWithoutCouponIncTax->value)->toBe(2040)
        ->and($result->total->value)->toBe(2040);
});

test('handle calculates cart totals with coupon and non coupon discount', function () {
    [$cart, $currency] = createCalculateCartWithLine(1000, 3, 20);

    $couponDiscount = Discount::factory()->create([
        'coupon' => 'COMBINED',
    ]);

    $nonCouponDiscount = Discount::factory()->create([
        'coupon' => null,
    ]);

    $line = $cart->lines->first();
    $line->subTotal = new DataTypesPrice(3000, $currency, 1);
    $line->subTotalDiscounted = new DataTypesPrice(2565, $currency, 1);
    $line->subTotalDiscountedWithoutCouponIncTax = new DataTypesPrice(3420, $currency, 1);
    $line->discountTotal = new DataTypesPrice(435, $currency, 1);
    $line->discountTotalWithoutCoupon = new DataTypesPrice(150, $currency, 1);
    $line->discountTotalWithoutCouponIncTax = new DataTypesPrice(180, $currency, 1);

    $cart->discountBreakdown = collect([
        (object) [
            'discount' => $nonCouponDiscount,
            'price' => new DataTypesPrice(150, $currency, 1),
        ],
        (object) [
            'discount' => $couponDiscount,
            'price' => new DataTypesPrice(285, $currency, 1),
        ],
    ]);
    $cart->shippingTotal = new DataTypesPrice(0, $currency, 1);

    $result = runCalculatePipeline($cart);

    expect($result->couponTotal->value)->toBe(285)
        ->and($result->couponTotalIncTax->value)->toBe(342)
        ->and($result->discountTotalWithoutCoupon->value)->toBe(150)
        ->and($result->discountTotalWithoutCouponIncTax->value)->toBe(180)
        ->and($result->subTotalDiscountedWithoutCouponIncTax->value)->toBe(3420)
        ->and($result->total->value)->toBe(3078);
});

test('handle calculates discount tax using fallback when line discount inc tax is missing', function () {
    Config::set('lunar.pricing.stored_inclusive_of_tax', false);

    [$cart, $currency] = createCalculateCartWithLine(2000, 1, 25);

    $discount = Discount::factory()->create([
        'coupon' => null,
    ]);

    $line = $cart->lines->first();
    $line->subTotal = new DataTypesPrice(2000, $currency, 1);
    $line->subTotalDiscounted = new DataTypesPrice(1500, $currency, 1);
    $line->subTotalDiscountedWithoutCouponIncTax = new DataTypesPrice(2375, $currency, 1);
    $line->discountTotal = new DataTypesPrice(500, $currency, 1);
    $line->discountTotalWithoutCoupon = new DataTypesPrice(500, $currency, 1);
    $line->discountTotalWithoutCouponIncTax = null;

    $cart->discountBreakdown = collect([
        (object) [
            'discount' => $discount,
            'price' => new DataTypesPrice(500, $currency, 1),
        ],
    ]);
    $cart->shippingTotal = new DataTypesPrice(0, $currency, 1);

    $result = runCalculatePipeline($cart);

    expect($result->discountTotalWithoutCoupon->value)->toBe(500)
        ->and($result->discountTotalWithoutCouponIncTax->value)->toBe(625)
        ->and($result->subTotalDiscountedWithoutCouponIncTax->value)->toBe(2375);
});

test('addTaxValue returns original value when pricing is inclusive', function () {
    Config::set('lunar.pricing.stored_inclusive_of_tax', true);

    $pipeline = app(Calculate::class);
    $reflection = new ReflectionClass($pipeline);
    $method = $reflection->getMethod('addTaxValue');

    $result = $method->invoke($pipeline, 1000, 0.20);

    expect($result)->toBe(1000);
});

test('addTaxValue adds tax when pricing is not inclusive', function () {
    Config::set('lunar.pricing.stored_inclusive_of_tax', false);

    $pipeline = app(Calculate::class);
    $reflection = new ReflectionClass($pipeline);
    $method = $reflection->getMethod('addTaxValue');

    $result = $method->invoke($pipeline, 1000, 0.20);

    expect($result)->toBe(1200);
});
