<?php

uses(\Lunar\Tests\Core\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Lunar\DiscountTypes\AdvancedAmountOff;
use Lunar\DiscountTypes\BuyXGetY;
use Lunar\Models\Cart;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Discount;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

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

function activateBuyXGetYDiscount(Discount $discount, Channel $channel, CustomerGroup $customerGroup): Discount
{
    $discount->channels()->attach([
        $channel->id => ['enabled' => true, 'starts_at' => now()->subMinute()],
    ]);

    $discount->customerGroups()->attach([
        $customerGroup->id => ['enabled' => true, 'starts_at' => now()->subMinute()],
    ]);

    return $discount;
}

function createPricedVariant(Currency $currency, int $price = 1000): ProductVariant
{
    $variant = ProductVariant::factory()->create([
        'purchasable' => 'always',
        'stock' => 100,
    ]);

    Price::factory()->create([
        'price' => $price,
        'min_quantity' => 1,
        'currency_id' => $currency->id,
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
    ]);

    return $variant->fresh(['product']);
}

test('can determine correct reward qty', function ($linesQuantity, $minQty, $rewardQty, $maxRewardQty, $expected) {
    $driver = new BuyXGetY;

    expect($driver->getRewardQuantity(
        $linesQuantity,
        $minQty,
        $rewardQty,
        $maxRewardQty ?? null
    ))->toEqual($expected);
})->with([
    [1, 1, 1, null, 1],
    [2, 1, 1, null, 2],
    [2, 2, 1, null, 1],
    [10, 10, 1, null, 1],
    [10, 1, 1, null, 10],
    [10, 1, 1, 5, 5],
    [3, 2, 1, 10, 1],
    [0, 1, 1, null, 0],
    [4, 5, 3, null, 0],
    [5, 5, 3, null, 3],
    [10, 5, 3, null, 6],
    [10, 5, 3, 5, 5],
]);

test('automatically adds reward when qualifying product is in the cart', function () {
    $condition = createPricedVariant($this->currency, 1000);
    $reward = createPricedVariant($this->currency, 500);

    $cart = Cart::factory()->create([
        'currency_id' => $this->currency->id,
        'channel_id' => $this->channel->id,
    ]);

    $cart->lines()->create([
        'purchasable_type' => $condition->getMorphClass(),
        'purchasable_id' => $condition->id,
        'quantity' => 1,
    ]);

    $discount = Discount::factory()->create([
        'type' => BuyXGetY::class,
        'coupon' => null,
        'starts_at' => now()->subMinute(),
        'data' => [
            'min_qty' => 1,
            'reward_qty' => 1,
            'automatically_add_rewards' => true,
        ],
    ]);

    activateBuyXGetYDiscount($discount, $this->channel, $this->customerGroup);

    $discount->discountables()->create([
        'discountable_type' => $condition->product->getMorphClass(),
        'discountable_id' => $condition->product->id,
        'type' => 'condition',
    ]);

    $discount->discountables()->create([
        'discountable_type' => $reward->product->getMorphClass(),
        'discountable_id' => $reward->product->id,
        'type' => 'reward',
    ]);

    $cart = $cart->calculate()->refresh()->load('lines');

    $giftLine = $cart->lines->first(function ($line) use ($reward) {
        return (int) $line->purchasable_id === (int) $reward->id;
    });

    $meta = json_decode(json_encode($giftLine?->meta), true) ?? [];

    expect($giftLine)->not->toBeNull()
        ->and(data_get($meta, 'added_by_discount.'.$discount->id))->not->toBeNull();
});

test('does not grant gift below min qty threshold and grants at threshold', function () {
    $condition = createPricedVariant($this->currency, 1000);
    $reward = createPricedVariant($this->currency, 500);

    $discount = Discount::factory()->create([
        'type' => BuyXGetY::class,
        'coupon' => null,
        'starts_at' => now()->subMinute(),
        'data' => [
            'min_qty' => 3,
            'reward_qty' => 1,
            'automatically_add_rewards' => true,
        ],
    ]);

    activateBuyXGetYDiscount($discount, $this->channel, $this->customerGroup);

    $discount->discountables()->create([
        'discountable_type' => $condition->product->getMorphClass(),
        'discountable_id' => $condition->product->id,
        'type' => 'condition',
    ]);

    $discount->discountables()->create([
        'discountable_type' => $reward->product->getMorphClass(),
        'discountable_id' => $reward->product->id,
        'type' => 'reward',
    ]);

    $cartBelow = Cart::factory()->create([
        'currency_id' => $this->currency->id,
        'channel_id' => $this->channel->id,
    ]);

    $cartBelow->lines()->create([
        'purchasable_type' => $condition->getMorphClass(),
        'purchasable_id' => $condition->id,
        'quantity' => 2,
    ]);

    $cartBelow = $cartBelow->calculate()->refresh()->load('lines');

    expect($cartBelow->lines->contains(fn ($line) => (int) $line->purchasable_id === (int) $reward->id))->toBeFalse();

    $cartAt = Cart::factory()->create([
        'currency_id' => $this->currency->id,
        'channel_id' => $this->channel->id,
    ]);

    $cartAt->lines()->create([
        'purchasable_type' => $condition->getMorphClass(),
        'purchasable_id' => $condition->id,
        'quantity' => 3,
    ]);

    $cartAt = $cartAt->calculate()->refresh()->load('lines');

    expect($cartAt->lines->contains(fn ($line) => (int) $line->purchasable_id === (int) $reward->id))->toBeTrue();
});

test('buy x get y can apply alongside advanced amount off without negative totals', function () {
    $condition = createPricedVariant($this->currency, 1000);
    $reward = createPricedVariant($this->currency, 400);
    $other = createPricedVariant($this->currency, 800);

    $cart = Cart::factory()->create([
        'currency_id' => $this->currency->id,
        'channel_id' => $this->channel->id,
    ]);

    $cart->lines()->create([
        'purchasable_type' => $condition->getMorphClass(),
        'purchasable_id' => $condition->id,
        'quantity' => 1,
    ]);

    $cart->lines()->create([
        'purchasable_type' => $other->getMorphClass(),
        'purchasable_id' => $other->id,
        'quantity' => 1,
    ]);

    $bxgy = Discount::factory()->create([
        'type' => BuyXGetY::class,
        'coupon' => null,
        'starts_at' => now()->subMinute(),
        'priority' => 1,
        'data' => [
            'min_qty' => 1,
            'reward_qty' => 1,
            'automatically_add_rewards' => true,
        ],
    ]);

    activateBuyXGetYDiscount($bxgy, $this->channel, $this->customerGroup);

    $bxgy->discountables()->create([
        'discountable_type' => $condition->product->getMorphClass(),
        'discountable_id' => $condition->product->id,
        'type' => 'condition',
    ]);

    $bxgy->discountables()->create([
        'discountable_type' => $reward->product->getMorphClass(),
        'discountable_id' => $reward->product->id,
        'type' => 'reward',
    ]);

    $amountOff = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now()->subMinute(),
        'priority' => 2,
        'data' => [
            'fixed_value' => false,
            'percentage' => 10,
        ],
    ]);

    activateBuyXGetYDiscount($amountOff, $this->channel, $this->customerGroup);

    $amountOff->discountables()->create([
        'discountable_type' => $other->product->getMorphClass(),
        'discountable_id' => $other->product->id,
        'type' => 'limitation',
    ]);

    $cart = $cart->calculate();

    expect($cart->total->value)->toBeGreaterThanOrEqual(0)
        ->and($cart->lines->every(fn ($line) => $line->subTotalDiscounted->value >= 0))->toBeTrue();
});

test('automatically adds every configured product reward when multiple rewards exist', function () {
    $condition = createPricedVariant($this->currency, 1000);
    $rewardA = createPricedVariant($this->currency, 500);
    $rewardB = createPricedVariant($this->currency, 400);

    $cart = Cart::factory()->create([
        'currency_id' => $this->currency->id,
        'channel_id' => $this->channel->id,
    ]);

    $cart->lines()->create([
        'purchasable_type' => $condition->getMorphClass(),
        'purchasable_id' => $condition->id,
        'quantity' => 1,
    ]);

    $discount = Discount::factory()->create([
        'type' => BuyXGetY::class,
        'coupon' => null,
        'starts_at' => now()->subMinute(),
        'data' => [
            'min_qty' => 1,
            'reward_qty' => 1,
            'automatically_add_rewards' => true,
        ],
    ]);

    activateBuyXGetYDiscount($discount, $this->channel, $this->customerGroup);

    $discount->discountables()->create([
        'discountable_type' => $condition->product->getMorphClass(),
        'discountable_id' => $condition->product->id,
        'type' => 'condition',
    ]);

    $discount->discountables()->create([
        'discountable_type' => $rewardA->product->getMorphClass(),
        'discountable_id' => $rewardA->product->id,
        'type' => 'reward',
    ]);

    $discount->discountables()->create([
        'discountable_type' => $rewardB->product->getMorphClass(),
        'discountable_id' => $rewardB->product->id,
        'type' => 'reward',
    ]);

    $cart = $cart->calculate();

    $giftIds = $cart->lines
        ->filter(function ($line) {
            $meta = $line->meta;
            $added = is_array($meta)
                ? ($meta['added_by_discount'] ?? null)
                : (is_object($meta) ? ($meta->added_by_discount ?? null) : null);

            return ! empty($added);
        })
        ->pluck('purchasable_id')
        ->map(fn ($id) => (int) $id)
        ->all();

    expect($giftIds)->toContain((int) $rewardA->id)
        ->and($giftIds)->toContain((int) $rewardB->id)
        ->and($cart->lines)->toHaveCount(3);
});
