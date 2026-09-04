<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Lunar\Base\DataTransferObjects\CartDiscount;
use Lunar\Base\DiscountManagerInterface;
use Lunar\DiscountTypes\AdvancedAmountOff;
use Lunar\DiscountTypes\AmountOff;
use Lunar\Facades\Discounts;
use Lunar\Facades\StorefrontSession;
use Lunar\Managers\DiscountManager;
use Lunar\Models\Cart;
use Lunar\Models\CartLine;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Discount;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Lunar\Models\TaxRate;
use Lunar\Models\TaxRateAmount;
use Lunar\Models\TaxZone;
use Lunar\Tests\Core\Stubs\TestDiscountType;
use Lunar\Tests\Core\TestCase;

uses(TestCase::class);

uses(RefreshDatabase::class);

test('can instantiate manager', function () {
    $manager = app(DiscountManagerInterface::class);
    expect($manager)->toBeInstanceOf(DiscountManager::class);
});

test('can set channel', function () {
    $manager = app(DiscountManagerInterface::class);

    $channel = Channel::factory()->create();

    expect($manager->getChannels())->toHaveCount(0);

    $manager->channel($channel);

    expect($manager->getChannels())->toHaveCount(1);

    $channels = Channel::factory(2)->create();

    $manager->channel($channels);

    expect($manager->getChannels())->toHaveCount(2);

    $this->expectException(InvalidArgumentException::class);

    $manager->channel(Product::factory(2)->create());
});

test('can set customer group', function () {
    $manager = app(DiscountManagerInterface::class);

    $customerGroup = CustomerGroup::factory()->create();

    expect($manager->getCustomerGroups())->toHaveCount(0);

    $manager->customerGroup($customerGroup);

    expect($manager->getCustomerGroups())->toHaveCount(1);

    $customerGroups = CustomerGroup::factory(2)->create();

    $manager->customerGroup($customerGroups);

    expect($manager->getCustomerGroups())->toHaveCount(2);

    $this->expectException(InvalidArgumentException::class);

    $manager->channel(Product::factory(2)->create());
});

test('can restrict discounts to channel', function () {
    $channel = Channel::factory()->create([
        'default' => true,
    ]);

    $channelTwo = Channel::factory()->create([
        'default' => false,
    ]);

    $customerGroup = CustomerGroup::factory()->create([
        'default' => true,
    ]);

    $discount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'data' => [
            'fixed_value' => false,
            'percentage' => 10,
        ],
    ]);

    $discount->customerGroups()->sync([
        $customerGroup->id => [
            'enabled' => false,
            'starts_at' => null,
        ],
    ]);

    $discount->channels()->sync([
        $channel->id => [
            'enabled' => false,
            'starts_at' => null,
        ],
        $channelTwo->id => [
            'enabled' => false,
            'starts_at' => null,
        ],
    ]);

    $manager = app(DiscountManagerInterface::class);

    expect($manager->getDiscounts())->toBeEmpty();

    $discount->customerGroups()->sync([
        $customerGroup->id => [
            'enabled' => true,
            'visible' => true,
            'starts_at' => now(),
        ],
    ]);

    $discount->channels()->sync([
        $channel->id => [
            'enabled' => true,
            'starts_at' => now(),
        ],
        $channelTwo->id => [
            'enabled' => false,
            'starts_at' => now(),
        ],
    ]);

    expect($manager->getDiscounts())->toHaveCount(1);

    $discount->channels()->sync([
        $channel->id => [
            'enabled' => true,
            'starts_at' => now()->addHour(),
        ],
        $channelTwo->id => [
            'enabled' => false,
            'starts_at' => now(),
        ],
    ]);

    expect($manager->getDiscounts())->toBeEmpty();

    $discount->channels()->sync([
        $channel->id => [
            'enabled' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now(),
        ],
        $channelTwo->id => [
            'enabled' => true,
            'starts_at' => now(),
        ],
    ]);

    expect($manager->getDiscounts())->toBeEmpty();

    $manager->channel($channelTwo);

    expect($manager->getDiscounts())->toHaveCount(1);
});

test('can restrict discounts to customer group', function () {
    $channel = Channel::factory()->create([
        'default' => true,
    ]);

    $customerGroup = CustomerGroup::factory()->create([
        'default' => true,
    ]);

    $customerGroupTwo = CustomerGroup::factory()->create([
        'default' => false,
    ]);

    $discount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'data' => [
            'fixed_value' => false,
            'percentage' => 10,
        ],
    ]);

    $discount->channels()->sync([
        $channel->id => [
            'enabled' => true,
            'starts_at' => now(),
        ],
    ]);

    $discount->customerGroups()->sync([
        $customerGroup->id => [
            'enabled' => true,
            'visible' => true,
            'starts_at' => now(),
        ],
    ]);

    $manager = app(DiscountManagerInterface::class);

    expect($manager->getDiscounts())->toHaveCount(1);

    $discount->customerGroups()->sync([
        $channel->id => [
            'visible' => false,
            'enabled' => false,
            'starts_at' => now(),
        ],
    ]);

    expect($manager->getDiscounts())->toBeEmpty();

    $discount->customerGroups()->sync([
        $customerGroup->id => [
            'enabled' => true,
            'visible' => true,
            'starts_at' => now()->addMinutes(1),
        ],
        $customerGroupTwo->id => [
            'enabled' => false,
            'visible' => false,
            'starts_at' => now()->addMinutes(1),
        ],
    ]);

    $manager->customerGroup($customerGroupTwo);

    expect($manager->getDiscounts())->toBeEmpty();
});

test('can fetch discount types', function () {
    $manager = app(DiscountManagerInterface::class);

    expect($manager->getTypes())->toBeInstanceOf(Collection::class);
});

test('can fetch applied discounts', function () {
    $manager = app(DiscountManagerInterface::class);

    expect($manager->getApplied())->toBeInstanceOf(Collection::class);
    expect($manager->getApplied())->toHaveCount(0);
});

test('can add applied discount', function () {
    $manager = app(DiscountManagerInterface::class);

    expect($manager->getApplied())->toBeInstanceOf(Collection::class);

    expect($manager->getApplied())->toHaveCount(0);

    ProductVariant::factory()->create();

    $discount = Discount::factory()->create();
    $cartLine = CartLine::factory()->create();

    $discount = new CartDiscount(
        model: $cartLine,
        discount: $discount
    );

    $manager->addApplied($discount);

    expect($manager->getApplied())->toHaveCount(1);
});

test('can add new types', function () {
    $manager = app(DiscountManagerInterface::class);

    $testType = $manager->getTypes()->first(function ($type) {
        return get_class($type) == TestDiscountType::class;
    });

    expect($testType)->toBeNull();

    $manager->addType(TestDiscountType::class);

    $testType = $manager->getTypes()->first(function ($type) {
        return get_class($type) == TestDiscountType::class;
    });

    expect($testType)->toBeInstanceOf(TestDiscountType::class);
});

test('can validate coupons', function () {
    $manager = app(DiscountManagerInterface::class);

    Discount::factory()->create([
        'type' => AmountOff::class,
        'name' => 'Test Coupon',
        'coupon' => '10OFF',
        'data' => [
            'fixed_value' => false,
            'percentage' => 10,
        ],
    ]);

    expect($manager->validateCoupon('10OFF'))->toBeTrue();

    expect($manager->validateCoupon('20OFF'))->toBeFalse();
});

test('can get discount with coupon', function () {
    $currency = Currency::factory()->create([
        'default' => true,
    ]);

    $customerGroup = CustomerGroup::factory()->create([
        'default' => true,
    ]);

    $channel = Channel::factory()->create([
        'default' => true,
    ]);

    $cart = Cart::factory()->create([
        'currency_id' => $currency->id,
        'channel_id' => $channel->id,
        'coupon_code' => null,
    ]);

    $purchasableA = ProductVariant::factory()->create();

    Price::factory()->create([
        'price' => 1000, // £10
        'min_quantity' => 1,
        'currency_id' => $currency->id,
        'priceable_type' => $purchasableA->getMorphClass(),
        'priceable_id' => $purchasableA->id,
    ]);

    $cart->lines()->create([
        'purchasable_type' => $purchasableA->getMorphClass(),
        'purchasable_id' => $purchasableA->id,
        'quantity' => 2,
    ]);

    $discountA = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'name' => 'Test Discount A',
        'coupon' => null,
        'starts_at' => now(),
        'data' => [
            'fixed_value' => true,
            'fixed_values' => [
                'GBP' => 10,
            ],
        ],
    ]);

    $discountA->channels()->attach([
        $channel->id => [
            'enabled' => true,
            'starts_at' => now(),
        ],
    ]);

    $discountA->customerGroups()->attach([
        $customerGroup->id => [
            'enabled' => true,
            'starts_at' => now(),
        ],
    ]);

    $discountB = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'name' => 'Test Discount B',
        'coupon' => null,
        'starts_at' => now(),
        'data' => [
            'fixed_value' => true,
            'fixed_values' => [
                'GBP' => 10,
            ],
        ],
    ]);

    $discountB->channels()->attach([
        $channel->id => [
            'enabled' => true,
            'starts_at' => now(),
        ],
    ]);

    $discountB->customerGroups()->attach([
        $customerGroup->id => [
            'enabled' => true,
            'starts_at' => now(),
        ],
    ]);

    expect(Discounts::getDiscounts($cart))->toHaveCount(2);

    $discountA->update([
        'coupon' => 'ABCD',
    ]);

    $discountB->update([
        'coupon' => 'ABCDEF',
    ]);

    $cart->update([
        'coupon_code' => 'ABCDEF',
    ]);

    expect(Discounts::getDiscounts($cart->refresh()))->toHaveCount(2);
});

test('applies a coupon-less fixed value discount to cart lines automatically', function () {
    $currency = Currency::factory()->create([
        'code' => 'GBP',
        'decimal_places' => 2,
        'default' => true,
    ]);

    $customerGroup = CustomerGroup::factory()->create([
        'default' => true,
    ]);

    $channel = Channel::factory()->create([
        'default' => true,
    ]);

    $cart = Cart::factory()->create([
        'currency_id' => $currency->id,
        'channel_id' => $channel->id,
    ]);

    $variant = ProductVariant::factory()->create();

    Price::factory()->create([
        'price' => 1000, // £10
        'min_quantity' => 1,
        'currency_id' => $currency->id,
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
    ]);

    $cart->lines()->create([
        'purchasable_type' => $variant->getMorphClass(),
        'purchasable_id' => $variant->id,
        'quantity' => 1,
    ]);

    $discount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now(),
        'data' => [
            'fixed_value' => true,
            'fixed_values' => ['GBP' => 200],
        ],
    ]);

    $discount->channels()->attach([
        $channel->id => ['enabled' => true, 'starts_at' => now()->subMinute()],
    ]);

    $discount->customerGroups()->attach([
        $customerGroup->id => ['enabled' => true, 'starts_at' => now()->subMinute()],
    ]);

    $cart = $cart->calculate();

    // Today this silently no-ops without the applyFixedValueForLine fix.
    expect($cart->lines->first()->subTotalDiscounted->value)->toEqual(800)
        ->and($cart->lines->first()->discountTotal->value)->toEqual(200);
});

test('an eligible percentage discount wins the line when the only fixed value discount is rejected by the minimum remaining percentage', function () {
    $currency = Currency::factory()->create([
        'code' => 'GBP',
        'decimal_places' => 2,
        'default' => true,
    ]);

    $customerGroup = CustomerGroup::factory()->create([
        'default' => true,
    ]);

    $channel = Channel::factory()->create([
        'default' => true,
    ]);

    $cart = Cart::factory()->create([
        'currency_id' => $currency->id,
        'channel_id' => $channel->id,
    ]);

    $variant = ProductVariant::factory()->create();

    Price::factory()->create([
        'price' => 300, // £3
        'min_quantity' => 1,
        'currency_id' => $currency->id,
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
    ]);

    $cart->lines()->create([
        'purchasable_type' => $variant->getMorphClass(),
        'purchasable_id' => $variant->id,
        'quantity' => 1,
    ]);

    $percentageDiscount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now(),
        'data' => [
            'fixed_value' => false,
            'percentage' => 5,
        ],
    ]);

    $fixedDiscount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now(),
        'data' => [
            'fixed_value' => true,
            // Would leave 20 (6.7%), below the default 10% minimum
            // remaining -> rejected outright, estimated amount is 0.
            'fixed_values' => ['GBP' => 280],
        ],
    ]);

    foreach ([$percentageDiscount, $fixedDiscount] as $discount) {
        $discount->channels()->attach([
            $channel->id => ['enabled' => true, 'starts_at' => now()->subMinute()],
        ]);

        $discount->customerGroups()->attach([
            $customerGroup->id => ['enabled' => true, 'starts_at' => now()->subMinute()],
        ]);
    }

    $cart = $cart->calculate();

    // 5% of £3 = 15, the rejected fixed value discount (estimated at 0)
    // never wins, so the percentage discount applies instead.
    expect($cart->lines->first()->discountTotal->value)->toEqual(15);
});

test('no discount applies when the only candidate is a fixed value discount rejected by the minimum remaining percentage', function () {
    $currency = Currency::factory()->create([
        'code' => 'GBP',
        'decimal_places' => 2,
        'default' => true,
    ]);

    $customerGroup = CustomerGroup::factory()->create([
        'default' => true,
    ]);

    $channel = Channel::factory()->create([
        'default' => true,
    ]);

    $cart = Cart::factory()->create([
        'currency_id' => $currency->id,
        'channel_id' => $channel->id,
    ]);

    $variant = ProductVariant::factory()->create();

    Price::factory()->create([
        'price' => 300, // £3
        'min_quantity' => 1,
        'currency_id' => $currency->id,
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
    ]);

    $cart->lines()->create([
        'purchasable_type' => $variant->getMorphClass(),
        'purchasable_id' => $variant->id,
        'quantity' => 1,
    ]);

    $discount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now(),
        'data' => [
            'fixed_value' => true,
            // Would leave 20 (6.7%), below the default 10% minimum
            // remaining -> rejected outright.
            'fixed_values' => ['GBP' => 280],
        ],
    ]);

    $discount->channels()->attach([
        $channel->id => ['enabled' => true, 'starts_at' => now()->subMinute()],
    ]);

    $discount->customerGroups()->attach([
        $customerGroup->id => ['enabled' => true, 'starts_at' => now()->subMinute()],
    ]);

    $cart = $cart->calculate();

    $line = $cart->lines->first();

    expect($line->discountTotal?->value ?: 0)->toEqual(0)
        ->and($line->subTotalDiscounted?->value ?: $line->subTotal->value)->toEqual(300);
});

test('ranks a mixed percentage and fixed value discount by their actual line amount', function () {
    $currency = Currency::factory()->create([
        'code' => 'GBP',
        'decimal_places' => 2,
        'default' => true,
    ]);

    $customerGroup = CustomerGroup::factory()->create([
        'default' => true,
    ]);

    $channel = Channel::factory()->create([
        'default' => true,
    ]);

    $cart = Cart::factory()->create([
        'currency_id' => $currency->id,
        'channel_id' => $channel->id,
    ]);

    $variant = ProductVariant::factory()->create();

    Price::factory()->create([
        'price' => 1000, // £10
        'min_quantity' => 1,
        'currency_id' => $currency->id,
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
    ]);

    $cart->lines()->create([
        'purchasable_type' => $variant->getMorphClass(),
        'purchasable_id' => $variant->id,
        'quantity' => 1,
    ]);

    $percentageDiscount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now(),
        'data' => [
            'fixed_value' => false,
            'percentage' => 10,
        ],
    ]);

    $fixedDiscount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now(),
        'data' => [
            'fixed_value' => true,
            'fixed_values' => ['GBP' => 50],
        ],
    ]);

    foreach ([$percentageDiscount, $fixedDiscount] as $discount) {
        $discount->channels()->attach([
            $channel->id => ['enabled' => true, 'starts_at' => now()->subMinute()],
        ]);

        $discount->customerGroups()->attach([
            $customerGroup->id => ['enabled' => true, 'starts_at' => now()->subMinute()],
        ]);
    }

    $cart = $cart->calculate();

    // 10% of £10 = £1 (100), fixed value is £0.50 (50) -> percentage wins.
    expect($cart->lines->first()->discountTotal->value)->toEqual(100);

    $fixedDiscount->update([
        'data' => [
            'fixed_value' => true,
            'fixed_values' => ['GBP' => 200],
        ],
    ]);

    Discounts::resetDiscounts();

    $cart = $cart->refresh()->calculate();

    // Fixed value is now £2 (200), bigger than the £1 (100) percentage discount -> fixed value wins.
    expect($cart->lines->first()->discountTotal->value)->toEqual(200);
});

test('ranks a mixed percentage and fixed value discount the same way regardless of cart line quantity', function () {
    // LFP-760 follow-up: getHighestValueDiscount() now compares candidates on
    // a per-unit basis (estimateDiscountAmountPerUnitForLine()), matching the
    // catalog preview, which has no quantity concept and always reasons about
    // a single unit. Note: dividing both candidates' line-total estimate by
    // the same quantity doesn't change which one is larger today (it's a
    // uniform scale-down), so this test won't fail on the pre-change code --
    // it locks in the per-unit comparison as an explicit, quantity-invariant
    // property rather than an implicit consequence of the current formulas,
    // so a future change to either formula (e.g. a flat per-line component)
    // can't silently break parity between the two ranking paths.
    $currency = Currency::factory()->create([
        'code' => 'GBP',
        'decimal_places' => 2,
        'default' => true,
    ]);

    $customerGroup = CustomerGroup::factory()->create([
        'default' => true,
    ]);

    $channel = Channel::factory()->create([
        'default' => true,
    ]);

    $cart = Cart::factory()->create([
        'currency_id' => $currency->id,
        'channel_id' => $channel->id,
    ]);

    $variant = ProductVariant::factory()->create();

    Price::factory()->create([
        'price' => 1000, // £10 per unit
        'min_quantity' => 1,
        'currency_id' => $currency->id,
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
    ]);

    $cart->lines()->create([
        'purchasable_type' => $variant->getMorphClass(),
        'purchasable_id' => $variant->id,
        'quantity' => 3,
    ]);

    $percentageDiscount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now(),
        'data' => [
            'fixed_value' => false,
            'percentage' => 10,
        ],
    ]);

    $fixedDiscount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now(),
        'data' => [
            'fixed_value' => true,
            'fixed_values' => ['GBP' => 50], // £0.50 per unit
        ],
    ]);

    foreach ([$percentageDiscount, $fixedDiscount] as $discount) {
        $discount->channels()->attach([
            $channel->id => ['enabled' => true, 'starts_at' => now()->subMinute()],
        ]);

        $discount->customerGroups()->attach([
            $customerGroup->id => ['enabled' => true, 'starts_at' => now()->subMinute()],
        ]);
    }

    $manager = app(DiscountManagerInterface::class);

    // Per unit, 10% of £10 (£1) beats the £0.50 fixed value -> percentage
    // wins, both for a single unit (catalog preview) and for the qty-3 line.
    expect($manager->getDiscountForPurchasable($variant->fresh())->id)->toEqual($percentageDiscount->id);

    $cart = $cart->calculate();

    // £1/unit * 3 units = £3 (300), not the £1.50 (150) a per-unit fixed
    // value would give -> confirms the percentage discount, not the fixed
    // one, was applied to the qty-3 line.
    expect($cart->lines->first()->discountTotal->value)->toEqual(300);

    $fixedDiscount->update([
        'data' => [
            'fixed_value' => true,
            'fixed_values' => ['GBP' => 200], // £2 per unit, now bigger than the £1/unit percentage
        ],
    ]);

    // Fresh manager instance: getDiscountForPurchasable() memoizes per
    // purchasable, and that cache isn't cleared by resetDiscounts(). The
    // Discounts facade (used internally by cart calculation) also caches its
    // resolved instance separately from the container, so both must be
    // cleared for $manager and the upcoming cart->calculate() call to agree.
    app()->forgetInstance(DiscountManagerInterface::class);
    \Illuminate\Support\Facades\Facade::clearResolvedInstances();
    $manager = app(DiscountManagerInterface::class);

    // Per unit, £2 fixed now beats the £1 percentage -> fixed value wins,
    // again consistently for both the catalog preview and the qty-3 line.
    expect($manager->getDiscountForPurchasable($variant->fresh())->id)->toEqual($fixedDiscount->id);

    $cart = $cart->refresh()->calculate();

    // £2/unit * 3 units = £6 (600).
    expect($cart->lines->first()->discountTotal->value)->toEqual(600);
});

test('getDiscountForPurchasable selects a coupon-less fixed value discount without a cart line', function () {
    $currency = Currency::factory()->create([
        'code' => 'GBP',
        'decimal_places' => 2,
        'default' => true,
    ]);

    $customerGroup = CustomerGroup::factory()->create([
        'default' => true,
    ]);

    $channel = Channel::factory()->create([
        'default' => true,
    ]);

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
            'fixed_values' => ['GBP' => 200],
        ],
    ]);

    $discount->channels()->attach([
        $channel->id => ['enabled' => true, 'starts_at' => now()->subMinute()],
    ]);

    $discount->customerGroups()->attach([
        $customerGroup->id => ['enabled' => true, 'starts_at' => now()->subMinute()],
    ]);

    $manager = app(DiscountManagerInterface::class);

    expect($manager->getDiscountForPurchasable($variant->fresh()))
        ->not->toBeNull()
        ->id->toEqual($discount->id);
});

test('getDiscountForPurchasable ranks a percentage discount above a smaller fixed value discount without a cart line', function () {
    $currency = Currency::factory()->create([
        'code' => 'GBP',
        'decimal_places' => 2,
        'default' => true,
    ]);

    $customerGroup = CustomerGroup::factory()->create([
        'default' => true,
    ]);

    $channel = Channel::factory()->create([
        'default' => true,
    ]);

    $variant = ProductVariant::factory()->create();

    Price::factory()->create([
        'price' => 1000, // £10
        'min_quantity' => 1,
        'currency_id' => $currency->id,
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
    ]);

    $percentageDiscount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now(),
        'data' => [
            'fixed_value' => false,
            'percentage' => 10,
        ],
    ]);

    $fixedDiscount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now(),
        'data' => [
            'fixed_value' => true,
            'fixed_values' => ['GBP' => 50],
        ],
    ]);

    foreach ([$percentageDiscount, $fixedDiscount] as $discount) {
        $discount->channels()->attach([
            $channel->id => ['enabled' => true, 'starts_at' => now()->subMinute()],
        ]);

        $discount->customerGroups()->attach([
            $customerGroup->id => ['enabled' => true, 'starts_at' => now()->subMinute()],
        ]);
    }

    $manager = app(DiscountManagerInterface::class);

    // 10% of £10 = £1 (100), fixed value is £0.50 (50) -> percentage wins.
    expect($manager->getDiscountForPurchasable($variant->fresh())->id)->toEqual($percentageDiscount->id);
});

test('getDiscountForPurchasable ranks a fixed value discount above a smaller percentage discount without a cart line', function () {
    $currency = Currency::factory()->create([
        'code' => 'GBP',
        'decimal_places' => 2,
        'default' => true,
    ]);

    $customerGroup = CustomerGroup::factory()->create([
        'default' => true,
    ]);

    $channel = Channel::factory()->create([
        'default' => true,
    ]);

    $variant = ProductVariant::factory()->create();

    Price::factory()->create([
        'price' => 1000, // £10
        'min_quantity' => 1,
        'currency_id' => $currency->id,
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
    ]);

    $percentageDiscount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now(),
        'data' => [
            'fixed_value' => false,
            'percentage' => 10,
        ],
    ]);

    $fixedDiscount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now(),
        'data' => [
            'fixed_value' => true,
            'fixed_values' => ['GBP' => 200],
        ],
    ]);

    foreach ([$percentageDiscount, $fixedDiscount] as $discount) {
        $discount->channels()->attach([
            $channel->id => ['enabled' => true, 'starts_at' => now()->subMinute()],
        ]);

        $discount->customerGroups()->attach([
            $customerGroup->id => ['enabled' => true, 'starts_at' => now()->subMinute()],
        ]);
    }

    $manager = app(DiscountManagerInterface::class);

    // Fixed value is £2 (200), bigger than the £1 (100) percentage discount -> fixed value wins.
    expect($manager->getDiscountForPurchasable($variant->fresh())->id)->toEqual($fixedDiscount->id);
});

test('getDiscountForPurchasable ranks against the same price basis the cart uses when prices are stored inclusive of tax', function () {
    // LFP-760 regression: estimateDiscountValueForPurchasable() used to always
    // rank against the ex-tax price (Price::priceExTax()), while the cart
    // (estimateDiscountAmountPerUnitForLine()) ranks against the cart line subtotal,
    // which is the raw stored price -- gross when prices are stored inclusive
    // of tax. With LUNAR_STORE_INCLUSIVE_OF_TAX enabled and a non-zero tax
    // rate, those two bases differ, so a fixed-value discount and a
    // percentage discount could rank differently on the catalog vs the cart.
    config(['lunar.pricing.stored_inclusive_of_tax' => true]);

    $currency = Currency::factory()->create([
        'code' => 'RON',
        'decimal_places' => 2,
        'default' => true,
    ]);

    $customerGroup = CustomerGroup::factory()->create([
        'default' => true,
    ]);

    $channel = Channel::factory()->create([
        'default' => true,
    ]);

    $taxZone = TaxZone::factory()->create(['default' => true]);
    $taxRate = TaxRate::factory()->create(['tax_zone_id' => $taxZone->id]);
    $taxClass = TaxClass::factory()->create();
    TaxRateAmount::factory()->create([
        'tax_class_id' => $taxClass->id,
        'tax_rate_id' => $taxRate->id,
        'percentage' => 25,
    ]);

    $variant = ProductVariant::factory()->create([
        'tax_class_id' => $taxClass->id,
    ]);

    Price::factory()->create([
        'price' => 15000, // 150.00 RON, stored inclusive of tax
        'min_quantity' => 1,
        'currency_id' => $currency->id,
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
    ]);

    $fixedDiscount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now(),
        'data' => [
            'fixed_value' => true,
            'fixed_values' => ['RON' => 10000], // -100 RON
        ],
    ]);

    $percentageDiscount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now(),
        'data' => [
            'fixed_value' => false,
            'percentage' => 80,
        ],
    ]);

    foreach ([$fixedDiscount, $percentageDiscount] as $discount) {
        $discount->channels()->attach([
            $channel->id => ['enabled' => true, 'starts_at' => now()->subMinute()],
        ]);

        $discount->customerGroups()->attach([
            $customerGroup->id => ['enabled' => true, 'starts_at' => now()->subMinute()],
        ]);
    }

    $manager = app(DiscountManagerInterface::class);

    // Against the gross (stored, tax-inclusive) 150 RON price, 80% off
    // removes 120 RON, more than the fixed 100 RON -> percentage wins. This
    // must match what the cart would pick for the same line, since the cart
    // subtotal is also the gross stored price under inclusive-of-tax pricing.
    expect($manager->getDiscountForPurchasable($variant->fresh())->id)->toEqual($percentageDiscount->id);
});

test('getDiscountForPurchasable ranks discounts against the active storefront currency, not the default currency', function () {
    $defaultCurrency = Currency::factory()->create([
        'code' => 'GBP',
        'decimal_places' => 2,
        'default' => true,
    ]);

    $storefrontCurrency = Currency::factory()->create([
        'code' => 'EUR',
        'decimal_places' => 2,
        'default' => false,
    ]);

    $customerGroup = CustomerGroup::factory()->create([
        'default' => true,
    ]);

    $channel = Channel::factory()->create([
        'default' => true,
    ]);

    $variant = ProductVariant::factory()->create();

    // £10 in the default currency, but €30 in the active storefront currency.
    Price::factory()->create([
        'price' => 1000,
        'min_quantity' => 1,
        'currency_id' => $defaultCurrency->id,
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
    ]);

    Price::factory()->create([
        'price' => 3000,
        'min_quantity' => 1,
        'currency_id' => $storefrontCurrency->id,
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
    ]);

    $percentageDiscount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now(),
        'data' => [
            'fixed_value' => false,
            'percentage' => 10,
        ],
    ]);

    // £9 in the default currency (leaves exactly the 10% minimum remaining
    // of £10, so it's still eligible and wins there), but only €2 in the
    // active storefront currency (loses to 10% of €30 = €3 there).
    $fixedDiscount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now(),
        'data' => [
            'fixed_value' => true,
            'fixed_values' => [
                'GBP' => 900,
                'EUR' => 200,
            ],
        ],
    ]);

    foreach ([$percentageDiscount, $fixedDiscount] as $discount) {
        $discount->channels()->attach([
            $channel->id => ['enabled' => true, 'starts_at' => now()->subMinute()],
        ]);

        $discount->customerGroups()->attach([
            $customerGroup->id => ['enabled' => true, 'starts_at' => now()->subMinute()],
        ]);
    }

    StorefrontSession::setCurrency($storefrontCurrency);

    $manager = app(DiscountManagerInterface::class);

    // Ranking against the default currency (GBP) would pick the fixed value
    // discount (£9 > £1). Ranking against the active storefront currency
    // (EUR) must pick the percentage discount instead (€3 > €2), matching
    // what a cart checking out in EUR would select.
    expect($manager->getDiscountForPurchasable($variant->fresh())->id)->toEqual($percentageDiscount->id);
});

test('stop flag halts further discounts after a discount applies', function () {
    Currency::factory()->create([
        'code' => 'GBP',
        'decimal_places' => 2,
    ]);

    $channel = Channel::factory()->create([
        'default' => true,
    ]);

    $customerGroup = CustomerGroup::factory()->create([
        'default' => true,
    ]);

    $cart = Cart::factory()->create([
        'channel_id' => $channel->id,
        'currency_id' => Currency::getDefault()->id,
    ]);

    $purchasable = ProductVariant::factory()->create([
        'product_id' => Product::factory(),
    ]);

    Price::factory()->create([
        'price' => 1000,
        'min_quantity' => 1,
        'currency_id' => Currency::getDefault()->id,
        'priceable_type' => $purchasable->getMorphClass(),
        'priceable_id' => $purchasable->id,
    ]);

    $cart->lines()->create([
        'purchasable_type' => $purchasable->getMorphClass(),
        'purchasable_id' => $purchasable->id,
        'quantity' => 1,
    ]);

    $stopper = Discount::factory()->create([
        'type' => AmountOff::class,
        'name' => 'Stopper',
        'priority' => 10,
        'stop' => true,
        'data' => [
            'fixed_value' => false,
            'percentage' => 5,
        ],
    ]);

    $shouldNotApply = Discount::factory()->create([
        'type' => AmountOff::class,
        'name' => 'Should not apply',
        'priority' => 5,
        'stop' => false,
        'data' => [
            'fixed_value' => false,
            'percentage' => 20,
        ],
    ]);

    foreach ([$stopper, $shouldNotApply] as $discount) {
        $discount->customerGroups()->sync([
            $customerGroup->id => [
                'enabled' => true,
                'starts_at' => now(),
            ],
        ]);

        $discount->channels()->sync([
            $channel->id => [
                'enabled' => true,
                'starts_at' => now(),
            ],
        ]);
    }

    $cart->calculate();

    expect($cart->discounts)->toHaveCount(1);
    expect($cart->discounts->first()->discount->name)->toBe('Stopper');
});

test('stop flag does not halt further discounts when conditions fail', function () {
    Currency::factory()->create([
        'code' => 'GBP',
        'decimal_places' => 2,
    ]);

    $channel = Channel::factory()->create([
        'default' => true,
    ]);

    $customerGroup = CustomerGroup::factory()->create([
        'default' => true,
    ]);

    $cart = Cart::factory()->create([
        'channel_id' => $channel->id,
        'currency_id' => Currency::getDefault()->id,
    ]);

    $purchasable = ProductVariant::factory()->create([
        'product_id' => Product::factory(),
    ]);

    Price::factory()->create([
        'price' => 1000,
        'min_quantity' => 1,
        'currency_id' => Currency::getDefault()->id,
        'priceable_type' => $purchasable->getMorphClass(),
        'priceable_id' => $purchasable->id,
    ]);

    $cart->lines()->create([
        'purchasable_type' => $purchasable->getMorphClass(),
        'purchasable_id' => $purchasable->id,
        'quantity' => 1,
    ]);

    $couponed = Discount::factory()->create([
        'type' => AmountOff::class,
        'name' => 'Coupon discount that wont match',
        'priority' => 10,
        'stop' => true,
        'coupon' => 'WRONG',
        'data' => [
            'fixed_value' => false,
            'percentage' => 20,
        ],
    ]);

    $fallback = Discount::factory()->create([
        'type' => AmountOff::class,
        'name' => 'Fallback',
        'priority' => 5,
        'stop' => false,
        'data' => [
            'fixed_value' => false,
            'percentage' => 10,
        ],
    ]);

    foreach ([$couponed, $fallback] as $discount) {
        $discount->customerGroups()->sync([
            $customerGroup->id => [
                'enabled' => true,
                'starts_at' => now(),
            ],
        ]);

        $discount->channels()->sync([
            $channel->id => [
                'enabled' => true,
                'starts_at' => now(),
            ],
        ]);
    }

    $cart->calculate();

    expect($cart->discounts)->toHaveCount(1);
    expect($cart->discounts->first()->discount->name)->toBe('Fallback');
});

test('stop=false discount lets further discounts apply', function () {
    Currency::factory()->create([
        'code' => 'GBP',
        'decimal_places' => 2,
    ]);

    $channel = Channel::factory()->create([
        'default' => true,
    ]);

    $customerGroup = CustomerGroup::factory()->create([
        'default' => true,
    ]);

    $cart = Cart::factory()->create([
        'channel_id' => $channel->id,
        'currency_id' => Currency::getDefault()->id,
    ]);

    $purchasable = ProductVariant::factory()->create([
        'product_id' => Product::factory(),
    ]);

    Price::factory()->create([
        'price' => 1000,
        'min_quantity' => 1,
        'currency_id' => Currency::getDefault()->id,
        'priceable_type' => $purchasable->getMorphClass(),
        'priceable_id' => $purchasable->id,
    ]);

    $cart->lines()->create([
        'purchasable_type' => $purchasable->getMorphClass(),
        'purchasable_id' => $purchasable->id,
        'quantity' => 1,
    ]);

    $first = Discount::factory()->create([
        'type' => AmountOff::class,
        'name' => 'First',
        'priority' => 10,
        'stop' => false,
        'data' => [
            'fixed_value' => false,
            'percentage' => 10,
        ],
    ]);

    $second = Discount::factory()->create([
        'type' => AmountOff::class,
        'name' => 'Second',
        'priority' => 5,
        'stop' => false,
        'data' => [
            'fixed_value' => false,
            'percentage' => 20,
        ],
    ]);

    foreach ([$first, $second] as $discount) {
        $discount->customerGroups()->sync([
            $customerGroup->id => [
                'enabled' => true,
                'starts_at' => now(),
            ],
        ]);

        $discount->channels()->sync([
            $channel->id => [
                'enabled' => true,
                'starts_at' => now(),
            ],
        ]);
    }

    $cart->calculate();

    expect($cart->discounts)->toHaveCount(2);
});
