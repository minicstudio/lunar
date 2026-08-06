<?php

uses(\Lunar\Tests\Core\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Lunar\DataTypes\Price as PriceDataType;
use Lunar\DiscountTypes\AdvancedAmountOff;
use Lunar\Models\Cart;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Discount;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

function makeCartWithLine(Currency $currency, Channel $channel, int $price, int $quantity = 1): array
{
    $cart = Cart::factory()->create([
        'currency_id' => $currency->id,
        'channel_id' => $channel->id,
    ]);

    $variant = ProductVariant::factory()->create();

    Price::factory()->create([
        'price' => $price,
        'min_quantity' => 1,
        'currency_id' => $currency->id,
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
    ]);

    $cart->lines()->create([
        'purchasable_type' => $variant->getMorphClass(),
        'purchasable_id' => $variant->id,
        'quantity' => $quantity,
    ]);

    return [$cart, $variant];
}

function activateDiscount(Discount $discount, Channel $channel, CustomerGroup $customerGroup): Discount
{
    $discount->channels()->attach([
        $channel->id => ['enabled' => true, 'starts_at' => now()->subMinute()],
    ]);

    $discount->customerGroups()->attach([
        $customerGroup->id => ['enabled' => true, 'starts_at' => now()->subMinute()],
    ]);

    return $discount;
}

beforeEach(function () {
    $this->currency = Currency::factory()->create([
        'code' => 'GBP',
        'decimal_places' => 2,
        'default' => true,
    ]);

    $this->channel = Channel::factory()->create([
        'default' => true,
    ]);

    $this->customerGroup = CustomerGroup::factory()->create([
        'default' => true,
    ]);
});

test('applies fixed value discount per unit without a coupon code when the minimum remaining percentage is satisfied', function () {
    [$cart] = makeCartWithLine($this->currency, $this->channel, 300, 1);

    $discount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now(),
        'data' => [
            'fixed_value' => true,
            'fixed_values' => ['GBP' => 100],
        ],
    ]);

    activateDiscount($discount, $this->channel, $this->customerGroup);

    $cart = $cart->calculate();

    $line = $cart->lines->first();

    // Price 300 (£3.00), discount 100 (£1.00) leaves 200 (66.7%) — well
    // above the default 10% minimum remaining, so it applies in full.
    expect($line->discountTotal->value)->toEqual(100)
        ->and($line->subTotalDiscounted->value)->toEqual(200);
});

test('rejects a fixed value discount per unit that would leave less than the minimum remaining percentage', function () {
    [$cart] = makeCartWithLine($this->currency, $this->channel, 300, 1);

    $discount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now(),
        'data' => [
            'fixed_value' => true,
            // Price 300 (£3.00), discount 280 (£2.80) leaves 20 (6.7%),
            // below the default 10% minimum remaining — must be rejected
            // entirely, not partially applied.
            'fixed_values' => ['GBP' => 280],
        ],
    ]);

    activateDiscount($discount, $this->channel, $this->customerGroup);

    $cart = $cart->calculate();

    $line = $cart->lines->first();

    expect($line->discountTotal?->value ?: 0)->toEqual(0)
        ->and($line->subTotalDiscounted?->value ?: $line->subTotal->value)->toEqual(300);
});

test('applies a fixed value discount that leaves exactly the minimum remaining percentage', function () {
    [$cart] = makeCartWithLine($this->currency, $this->channel, 300, 1);

    $discount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now(),
        'data' => [
            'fixed_value' => true,
            // Price 300, discount 270 leaves exactly 30 (10% of 300) — the
            // boundary should still be eligible.
            'fixed_values' => ['GBP' => 270],
        ],
    ]);

    activateDiscount($discount, $this->channel, $this->customerGroup);

    $cart = $cart->calculate();

    $line = $cart->lines->first();

    expect($line->discountTotal->value)->toEqual(270)
        ->and($line->subTotalDiscounted->value)->toEqual(30);
});

test('never applies a fixed value discount that exceeds the line price', function () {
    [$cart] = makeCartWithLine($this->currency, $this->channel, 300, 1);

    $discount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now(),
        'data' => [
            'fixed_value' => true,
            'fixed_values' => ['GBP' => 500],
        ],
    ]);

    activateDiscount($discount, $this->channel, $this->customerGroup);

    $cart = $cart->calculate();

    $line = $cart->lines->first();

    expect($line->discountTotal?->value ?: 0)->toEqual(0)
        ->and($line->subTotalDiscounted?->value ?: $line->subTotal->value)->toEqual(300);
});

test('multiplies the fixed value by line quantity before evaluating eligibility', function () {
    [$cart] = makeCartWithLine($this->currency, $this->channel, 100, 3);

    $discount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now(),
        'data' => [
            'fixed_value' => true,
            'fixed_values' => ['GBP' => 80],
        ],
    ]);

    activateDiscount($discount, $this->channel, $this->customerGroup);

    $cart = $cart->calculate();

    $line = $cart->lines->first();

    // Line subtotal 300 (3 x £1.00), discount 80/unit x 3 = 240, leaving 60
    // (20%) — above the default 10% minimum remaining, so applies in full.
    expect($line->discountTotal->value)->toEqual(240)
        ->and($line->subTotalDiscounted->value)->toEqual(60);
});

test('applies an odd-cent fixed value discount to a cart line without truncation', function () {
    [$cart] = makeCartWithLine($this->currency, $this->channel, 5000, 1);

    $discount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now(),
        'data' => [
            'fixed_value' => true,
            'fixed_values' => ['GBP' => 1999],
        ],
    ]);

    activateDiscount($discount, $this->channel, $this->customerGroup);

    $cart = $cart->calculate();

    $line = $cart->lines->first();

    // Price 5000 (£50.00), discount 1999 (£19.99), well above the minimum
    // remaining threshold.
    expect($line->discountTotal->value)->toEqual(1999)
        ->and($line->subTotalDiscounted->value)->toEqual(3001);
});

test('the minimum remaining percentage is configurable', function () {
    config(['lunar.discounts.fixed_value_minimum_remaining_percentage' => 50]);

    [$cart] = makeCartWithLine($this->currency, $this->channel, 300, 1);

    $discount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now(),
        'data' => [
            'fixed_value' => true,
            // Leaves 100 (33%), which satisfied the default 10% minimum but
            // no longer satisfies a stricter 50% minimum.
            'fixed_values' => ['GBP' => 200],
        ],
    ]);

    activateDiscount($discount, $this->channel, $this->customerGroup);

    $cart = $cart->calculate();

    $line = $cart->lines->first();

    expect($line->discountTotal?->value ?: 0)->toEqual(0)
        ->and($line->subTotalDiscounted?->value ?: $line->subTotal->value)->toEqual(300);
});

test('a 0% minimum remaining percentage allows the full price to be deducted, making the line free', function () {
    config(['lunar.discounts.fixed_value_minimum_remaining_percentage' => 0]);

    [$cart] = makeCartWithLine($this->currency, $this->channel, 300, 1);

    $discount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now(),
        'data' => [
            'fixed_value' => true,
            'fixed_values' => ['GBP' => 300],
        ],
    ]);

    activateDiscount($discount, $this->channel, $this->customerGroup);

    $cart = $cart->calculate();

    $line = $cart->lines->first();

    expect($line->discountTotal->value)->toEqual(300)
        ->and($line->subTotalDiscounted->value)->toEqual(0);
});

test('automatic fixed value discount only applies to lines matching a product limitation', function () {
    $productA = Product::factory()->create();
    $productB = Product::factory()->create();

    $cart = Cart::factory()->create([
        'currency_id' => $this->currency->id,
        'channel_id' => $this->channel->id,
    ]);

    $variantA = ProductVariant::factory()->create(['product_id' => $productA->id]);
    $variantB = ProductVariant::factory()->create(['product_id' => $productB->id]);

    foreach ([$variantA, $variantB] as $variant) {
        Price::factory()->create([
            'price' => 300,
            'min_quantity' => 1,
            'currency_id' => $this->currency->id,
            'priceable_type' => $variant->getMorphClass(),
            'priceable_id' => $variant->id,
        ]);

        $cart->lines()->create([
            'purchasable_type' => $variant->getMorphClass(),
            'purchasable_id' => $variant->id,
            'quantity' => 1,
        ]);
    }

    $discount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now(),
        'data' => [
            'fixed_value' => true,
            'fixed_values' => ['GBP' => 200],
        ],
    ]);

    $discount->discountables()->create([
        'discountable_type' => Product::morphName(),
        'discountable_id' => $productA->id,
        'type' => 'limitation',
    ]);

    activateDiscount($discount, $this->channel, $this->customerGroup);

    $cart = $cart->calculate();

    $lineA = $cart->lines->firstWhere('purchasable_id', $variantA->id);
    $lineB = $cart->lines->firstWhere('purchasable_id', $variantB->id);

    expect($lineA->subTotalDiscounted->value)->toEqual(100)
        ->and($lineB->discountTotal?->value ?: 0)->toEqual(0);
});

test('calculateDiscountedPrice applies a coupon-less fixed value discount for catalog preview when eligible', function () {
    $price = new PriceDataType(300, $this->currency, 1);

    $discounted = AdvancedAmountOff::calculateDiscountedPrice($price, [
        'fixed_value' => true,
        'fixed_values' => ['GBP' => 100],
    ]);

    expect($discounted)->toEqual(200);
});

test('calculateDiscountedPrice rejects a coupon-less fixed value discount below the minimum remaining percentage', function () {
    $price = new PriceDataType(300, $this->currency, 1);

    // Would leave 20 (6.7%), below the default 10% minimum remaining — the
    // catalog preview must show the unchanged original price, never a
    // partially-applied discount.
    $discounted = AdvancedAmountOff::calculateDiscountedPrice($price, [
        'fixed_value' => true,
        'fixed_values' => ['GBP' => 280],
    ]);

    expect($discounted)->toEqual(300);
});

test('calculateDiscountedPrice never applies a coupon-less fixed value discount that exceeds the price', function () {
    $price = new PriceDataType(300, $this->currency, 1);

    $discounted = AdvancedAmountOff::calculateDiscountedPrice($price, [
        'fixed_value' => true,
        'fixed_values' => ['GBP' => 500],
    ]);

    expect($discounted)->toEqual(300);
});

test('calculateDiscountedPrice allows a coupon-less fixed value discount to fully deduct the price when the minimum remaining percentage is 0', function () {
    config(['lunar.discounts.fixed_value_minimum_remaining_percentage' => 0]);

    $price = new PriceDataType(300, $this->currency, 1);

    $discounted = AdvancedAmountOff::calculateDiscountedPrice($price, [
        'fixed_value' => true,
        'fixed_values' => ['GBP' => 300],
    ]);

    expect($discounted)->toEqual(0);
});

test('calculateDiscountedPrice floors a coupon-bound fixed value discount at zero', function () {
    $price = new PriceDataType(300, $this->currency, 1);

    $discounted = AdvancedAmountOff::calculateDiscountedPrice($price, [
        'fixed_value' => true,
        'fixed_values' => ['GBP' => 1000],
    ], 'SAVE10');

    expect($discounted)->toEqual(0);
});

test('calculateDiscountedPrice does not apply the minimum remaining percentage guard to a coupon-bound fixed value discount', function () {
    $price = new PriceDataType(1000, $this->currency, 1);

    // A 10% minimum remaining would reject/limit a 950 deduction against a
    // 1000 price (leaving only 50, i.e. 5%), but a coupon-bound discount is
    // not subject to this guard, so the full 950 is deducted.
    $discounted = AdvancedAmountOff::calculateDiscountedPrice($price, [
        'fixed_value' => true,
        'fixed_values' => ['GBP' => 950],
    ], 'SAVE10');

    expect($discounted)->toEqual(50);
});

test('calculateDiscountedPrice does not truncate odd-cent fixed values', function () {
    $price = new PriceDataType(5000, $this->currency, 1);

    $discounted = AdvancedAmountOff::calculateDiscountedPrice($price, [
        'fixed_value' => true,
        'fixed_values' => ['GBP' => 1999],
    ], 'SAVE10');

    expect($discounted)->toEqual(3001);
});

test('calculateDiscountedPrice is unaffected for percentage discounts', function () {
    $price = new PriceDataType(1000, $this->currency, 1);

    $discounted = AdvancedAmountOff::calculateDiscountedPrice($price, [
        'fixed_value' => false,
        'percentage' => 10,
    ]);

    expect($discounted)->toEqual(900);
});
