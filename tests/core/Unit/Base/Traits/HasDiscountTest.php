<?php

uses(\Lunar\Tests\Core\TestCase::class);

use Lunar\DiscountTypes\AdvancedAmountOff;
use Lunar\Models\Channel;
use Lunar\Models\Collection;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Discount;
use Lunar\Models\Price;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Lunar\Models\TaxRate;
use Lunar\Models\TaxRateAmount;
use Lunar\Models\TaxZone;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function activateFixedValueDiscount(Discount $discount, Channel $channel, CustomerGroup $customerGroup): void
{
    $discount->channels()->attach([
        $channel->id => ['enabled' => true, 'starts_at' => now()->subMinute()],
    ]);

    $discount->customerGroups()->attach([
        $customerGroup->id => ['enabled' => true, 'starts_at' => now()->subMinute()],
    ]);
}

test('getDiscountLabels omits decimals for a whole-number fixed value discount', function () {
    config(['lunar.pricing.stored_inclusive_of_tax' => true]);

    $currency = Currency::factory()->create([
        'code' => 'GBP',
        'decimal_places' => 2,
        'default' => true,
    ]);

    $customerGroup = CustomerGroup::factory()->create(['default' => true]);
    $channel = Channel::factory()->create(['default' => true]);

    $variant = ProductVariant::factory()->create();

    Price::factory()->create([
        'price' => 1000, // £10
        'min_quantity' => 1,
        'currency_id' => $currency->id,
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
    ]);

    $discount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now(),
        'data' => [
            'fixed_value' => true,
            'fixed_values' => ['GBP' => 200], // £2 fixed discount
        ],
    ]);

    activateFixedValueDiscount($discount, $channel, $customerGroup);

    $labels = $variant->fresh()->getDiscountLabels();

    expect($labels->get('GBP'))->toEqual('-£2');
});

test('getDiscountLabels keeps decimals for a fractional fixed value discount', function () {
    config(['lunar.pricing.stored_inclusive_of_tax' => true]);

    $currency = Currency::factory()->create([
        'code' => 'GBP',
        'decimal_places' => 2,
        'default' => true,
    ]);

    $customerGroup = CustomerGroup::factory()->create(['default' => true]);
    $channel = Channel::factory()->create(['default' => true]);

    $variant = ProductVariant::factory()->create();

    Price::factory()->create([
        'price' => 1000, // £10
        'min_quantity' => 1,
        'currency_id' => $currency->id,
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
    ]);

    $discount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now(),
        'data' => [
            'fixed_value' => true,
            'fixed_values' => ['GBP' => 250], // £2.50 fixed discount
        ],
    ]);

    activateFixedValueDiscount($discount, $channel, $customerGroup);

    $labels = $variant->fresh()->getDiscountLabels();

    expect($labels->get('GBP'))->toEqual('-£2.50');
});

test('getDiscountLabels returns a percentage label for a percentage discount', function () {
    $currency = Currency::factory()->create([
        'code' => 'GBP',
        'decimal_places' => 2,
        'default' => true,
    ]);

    $customerGroup = CustomerGroup::factory()->create(['default' => true]);
    $channel = Channel::factory()->create(['default' => true]);

    $variant = ProductVariant::factory()->create();

    Price::factory()->create([
        'price' => 1000, // £10
        'min_quantity' => 1,
        'currency_id' => $currency->id,
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
    ]);

    $discount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now(),
        'data' => [
            'fixed_value' => false,
            'percentage' => 10,
        ],
    ]);

    activateFixedValueDiscount($discount, $channel, $customerGroup);

    $labels = $variant->fresh()->getDiscountLabels();

    expect($labels->get('GBP'))->toEqual('10%');
});

test('ex tax and inc tax discounted prices reconcile at the tax rate for a fixed value discount when prices are stored inclusive of tax', function () {
    config(['lunar.pricing.stored_inclusive_of_tax' => true]);

    $taxZone = TaxZone::factory()->create(['default' => true]);
    $taxRate = TaxRate::factory()->create(['tax_zone_id' => $taxZone->id]);
    $taxClass = TaxClass::factory()->create();

    TaxRateAmount::factory()->create([
        'tax_rate_id' => $taxRate->id,
        'tax_class_id' => $taxClass->id,
        'percentage' => 21,
    ]);

    $currency = Currency::factory()->create([
        'code' => 'GBP',
        'decimal_places' => 2,
        'default' => true,
    ]);

    $customerGroup = CustomerGroup::factory()->create(['default' => true]);
    $channel = Channel::factory()->create(['default' => true]);

    $variant = ProductVariant::factory()->create([
        'tax_class_id' => $taxClass->id,
    ]);

    Price::factory()->create([
        'price' => 12100, // £121.00 inc tax (£100 ex tax at 21%)
        'min_quantity' => 1,
        'currency_id' => $currency->id,
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
    ]);

    $discount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now(),
        'data' => [
            'fixed_value' => true,
            'fixed_values' => ['GBP' => 1000], // £10 off the inc-tax price
        ],
    ]);

    activateFixedValueDiscount($discount, $channel, $customerGroup);

    $variant = $variant->fresh();

    $exTax = $variant->getCurrentPrices()->first();
    $incTax = $variant->getCurrentPricesIncTax()->first();

    expect($incTax->value)->toEqual(11100); // £121.00 - £10.00
    expect($exTax->value)->toEqual((int) round($incTax->value / 1.21));
});

test('getDiscountLabels shows the inc tax discount amount when prices are stored exclusive of tax', function () {
    config(['lunar.pricing.stored_inclusive_of_tax' => false]);

    $taxZone = TaxZone::factory()->create(['default' => true]);
    $taxRate = TaxRate::factory()->create(['tax_zone_id' => $taxZone->id]);
    $taxClass = TaxClass::factory()->create();

    TaxRateAmount::factory()->create([
        'tax_rate_id' => $taxRate->id,
        'tax_class_id' => $taxClass->id,
        'percentage' => 21,
    ]);

    $currency = Currency::factory()->create([
        'code' => 'GBP',
        'decimal_places' => 2,
        'default' => true,
    ]);

    $customerGroup = CustomerGroup::factory()->create(['default' => true]);
    $channel = Channel::factory()->create(['default' => true]);

    $variant = ProductVariant::factory()->create([
        'tax_class_id' => $taxClass->id,
    ]);

    Price::factory()->create([
        'price' => 16258, // £162.58 ex tax (£196.72 inc tax at 21%)
        'min_quantity' => 1,
        'currency_id' => $currency->id,
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
    ]);

    $discount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now(),
        'data' => [
            'fixed_value' => true,
            'fixed_values' => ['GBP' => 10000], // £100 off the ex-tax price
        ],
    ]);

    activateFixedValueDiscount($discount, $channel, $customerGroup);

    $variant = $variant->fresh();

    // £100 off ex tax is £121 off inc tax, which is what the shopper actually saves.
    expect($variant->getDiscountAmounts()->first()->value)->toEqual(10000);
    expect($variant->getDiscountAmountsIncTax()->first()->value)->toEqual(12100);
    expect($variant->getDiscountLabels()->get('GBP'))->toEqual('-£121');
});

test('getDiscountLabels returns no label when the fixed value discount is not granted', function () {
    $currency = Currency::factory()->create([
        'code' => 'GBP',
        'decimal_places' => 2,
        'default' => true,
    ]);

    $customerGroup = CustomerGroup::factory()->create(['default' => true]);
    $channel = Channel::factory()->create(['default' => true]);

    $variant = ProductVariant::factory()->create();

    Price::factory()->create([
        'price' => 1000, // £10
        'min_quantity' => 1,
        'currency_id' => $currency->id,
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
    ]);

    $discount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now(),
        'data' => [
            'fixed_value' => true,
            'fixed_values' => ['GBP' => 9800], // more than the price, so never applied
        ],
    ]);

    activateFixedValueDiscount($discount, $channel, $customerGroup);

    expect($variant->fresh()->getDiscountLabels())->toBeEmpty();
});

test('a shipping-only discount does not hide a lower tier discount in the catalog', function () {
    $currency = Currency::factory()->create([
        'code' => 'GBP',
        'decimal_places' => 2,
        'default' => true,
    ]);

    $customerGroup = CustomerGroup::factory()->create(['default' => true]);
    $channel = Channel::factory()->create(['default' => true]);

    $variant = ProductVariant::factory()->create();

    Price::factory()->create([
        'price' => 1000, // £10
        'min_quantity' => 1,
        'currency_id' => $currency->id,
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
    ]);

    $collection = Collection::factory()->create();
    $variant->product->collections()->attach($collection->id);

    $shippingOnly = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now(),
        'data' => [
            'fixed_value' => false,
            'percentage' => null,
            'free_shipping' => true,
        ],
    ]);

    $shippingOnly->discountables()->create([
        'discountable_type' => ProductVariant::morphName(),
        'discountable_id' => $variant->id,
        'type' => 'limitation',
    ]);

    $collectionDiscount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now(),
        'data' => [
            'fixed_value' => false,
            'percentage' => 10,
        ],
    ]);

    $collectionDiscount->collections()->attach($collection->id, ['type' => 'limitation']);

    activateFixedValueDiscount($shippingOnly, $channel, $customerGroup);
    activateFixedValueDiscount($collectionDiscount, $channel, $customerGroup);

    $variant = $variant->fresh();

    expect($variant->hasDiscount())->toBeTrue()
        ->and($variant->getDiscountedPrices()->first()->value)->toBe(900)
        ->and($variant->getDiscountLabels()->get('GBP'))->toEqual('10%');
});

test('a product whose only discount is shipping-only shows no discount', function () {
    $currency = Currency::factory()->create([
        'code' => 'GBP',
        'decimal_places' => 2,
        'default' => true,
    ]);

    $customerGroup = CustomerGroup::factory()->create(['default' => true]);
    $channel = Channel::factory()->create(['default' => true]);

    $variant = ProductVariant::factory()->create();

    Price::factory()->create([
        'price' => 1000, // £10
        'min_quantity' => 1,
        'currency_id' => $currency->id,
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
    ]);

    $discount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now(),
        'data' => [
            'fixed_value' => false,
            'percentage' => null,
            'free_shipping' => true,
        ],
    ]);

    activateFixedValueDiscount($discount, $channel, $customerGroup);

    $variant = $variant->fresh();

    expect($variant->hasDiscount())->toBeFalse()
        ->and($variant->getDiscountLabels())->toBeEmpty()
        ->and($variant->getDiscountAmounts())->toBeEmpty()
        ->and($variant->getCurrentPrices()->first()->value)->toBe($variant->getOriginalPrices()->first()->value);
});
