<?php

uses(\Lunar\Tests\Core\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

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

/**
 * @return array{0: Discount, 1: ProductVariant, 2: ProductVariant}
 */
function makeDualUseBxgy(
    Channel $channel,
    CustomerGroup $customerGroup,
    Currency $currency,
    Product $conditionProduct,
    Product $rewardProduct,
    array $data = [],
): array {
    $conditionVariant = ProductVariant::factory()->create([
        'product_id' => $conditionProduct->id,
        'purchasable' => 'always',
    ]);

    $rewardVariant = $conditionProduct->is($rewardProduct)
        ? $conditionVariant
        : ProductVariant::factory()->create([
            'product_id' => $rewardProduct->id,
            'purchasable' => 'always',
        ]);

    Price::factory()->create([
        'price' => 1000,
        'min_quantity' => 1,
        'currency_id' => $currency->id,
        'priceable_type' => $conditionVariant->getMorphClass(),
        'priceable_id' => $conditionVariant->id,
    ]);

    if (! $conditionVariant->is($rewardVariant)) {
        Price::factory()->create([
            'price' => 500,
            'min_quantity' => 1,
            'currency_id' => $currency->id,
            'priceable_type' => $rewardVariant->getMorphClass(),
            'priceable_id' => $rewardVariant->id,
        ]);
    }

    $discount = Discount::factory()->create([
        'type' => BuyXGetY::class,
        'name' => 'Dual-use gift',
        'coupon' => null,
        'starts_at' => now()->subMinute(),
        'data' => array_merge([
            'min_qty' => 1,
            'reward_qty' => 1,
            'automatically_add_rewards' => true,
        ], $data),
    ]);

    $discount->channels()->attach([
        $channel->id => ['enabled' => true, 'starts_at' => now()->subMinute()],
    ]);

    $discount->customerGroups()->attach([
        $customerGroup->id => ['enabled' => true, 'starts_at' => now()->subMinute()],
    ]);

    $discount->discountableConditions()->create([
        'discountable_type' => $conditionProduct->getMorphClass(),
        'discountable_id' => $conditionProduct->id,
        'type' => 'condition',
    ]);

    $discount->discountableRewards()->create([
        'discountable_type' => $rewardProduct->getMorphClass(),
        'discountable_id' => $rewardProduct->id,
        'type' => 'reward',
    ]);

    return [$discount, $conditionVariant, $rewardVariant];
}

test('auto-add creates a separate free gift line when the reward SKU is already paid in the cart', function () {
    $food = Product::factory()->create();
    $comb = Product::factory()->create();

    [, $foodVariant, $combVariant] = makeDualUseBxgy(
        $this->channel,
        $this->customerGroup,
        $this->currency,
        $food,
        $comb,
    );

    $cart = Cart::factory()->create([
        'channel_id' => $this->channel->id,
        'currency_id' => $this->currency->id,
    ]);

    $cart->lines()->create([
        'purchasable_type' => $foodVariant->getMorphClass(),
        'purchasable_id' => $foodVariant->id,
        'quantity' => 1,
    ]);

    $cart->lines()->create([
        'purchasable_type' => $combVariant->getMorphClass(),
        'purchasable_id' => $combVariant->id,
        'quantity' => 1,
    ]);

    $cart = $cart->calculate();

    $combLines = $cart->lines->filter(
        fn ($line) => (int) $line->purchasable_id === (int) $combVariant->id
    );

    expect($combLines)->toHaveCount(2);

    $paid = $combLines->first(function ($line) {
        $meta = $line->meta;

        return empty(data_get($meta, 'added_by_discount'));
    });

    $gift = $combLines->first(function ($line) {
        $meta = $line->meta;

        return ! empty(data_get($meta, 'added_by_discount'));
    });

    expect($paid)->not->toBeNull()
        ->and($paid->quantity)->toBe(1)
        ->and((int) ($paid->discountTotal?->value ?? 0))->toBe(0)
        ->and($gift)->not->toBeNull()
        ->and($gift->quantity)->toBe(1)
        ->and($gift->discountTotal->value)->toBe(500)
        ->and($gift->subTotalDiscounted->value)->toBe(0)
        ->and((int) ($gift->subTotalDiscountedWithoutCoupon?->value ?? -1))->toBe(0)
        ->and((int) ($gift->subTotalDiscountedWithoutCouponIncTax?->value ?? -1))->toBe(0);
});

test('same-SKU BOGO auto-adds a separate free unit without gifting the paid line', function () {
    $shoes = Product::factory()->create();

    [, $shoesVariant] = makeDualUseBxgy(
        $this->channel,
        $this->customerGroup,
        $this->currency,
        $shoes,
        $shoes,
        [
            'min_qty' => 2,
            'reward_qty' => 1,
        ],
    );

    Price::query()
        ->where('priceable_type', $shoesVariant->getMorphClass())
        ->where('priceable_id', $shoesVariant->id)
        ->update(['price' => 2000]);

    $cart = Cart::factory()->create([
        'channel_id' => $this->channel->id,
        'currency_id' => $this->currency->id,
    ]);

    $cart->lines()->create([
        'purchasable_type' => $shoesVariant->getMorphClass(),
        'purchasable_id' => $shoesVariant->id,
        'quantity' => 2,
    ]);

    $cart = $cart->calculate();

    $shoeLines = $cart->lines->filter(
        fn ($line) => (int) $line->purchasable_id === (int) $shoesVariant->id
    );

    expect($shoeLines)->toHaveCount(2);

    $paid = $shoeLines->first(fn ($line) => empty(data_get($line->meta, 'added_by_discount')));
    $gift = $shoeLines->first(fn ($line) => ! empty(data_get($line->meta, 'added_by_discount')));

    expect($paid)->not->toBeNull()
        ->and($paid->quantity)->toBe(2)
        ->and((int) ($paid->discountTotal?->value ?? 0))->toBe(0)
        ->and($gift)->not->toBeNull()
        ->and($gift->quantity)->toBe(1)
        ->and($gift->discountTotal->value)->toBe(2000)
        ->and($gift->subTotalDiscounted->value)->toBe(0)
        ->and((int) ($gift->subTotalDiscountedWithoutCoupon?->value ?? -1))->toBe(0)
        ->and((int) ($gift->subTotalDiscountedWithoutCouponIncTax?->value ?? -1))->toBe(0);
});

test('gift lines do not inflate condition quantity for further rewards', function () {
    $product = Product::factory()->create();

    [, $variant] = makeDualUseBxgy(
        $this->channel,
        $this->customerGroup,
        $this->currency,
        $product,
        $product,
        [
            'min_qty' => 2,
            'reward_qty' => 1,
        ],
    );

    $cart = Cart::factory()->create([
        'channel_id' => $this->channel->id,
        'currency_id' => $this->currency->id,
    ]);

    $cart->lines()->create([
        'purchasable_type' => $variant->getMorphClass(),
        'purchasable_id' => $variant->id,
        'quantity' => 2,
    ]);

    $cart = $cart->calculate();

    // 2 paid units → 1 gift. Gift must not count toward another min_qty band.
    $giftLines = $cart->lines->filter(
        fn ($line) => ! empty(data_get($line->meta, 'added_by_discount'))
    );

    expect($giftLines)->toHaveCount(1)
        ->and($giftLines->sum('quantity'))->toBe(1)
        ->and($cart->lines->sum('quantity'))->toBe(3);
});

test('recalculate does not keep growing an existing dual-use gift line', function () {
    $food = Product::factory()->create();
    $comb = Product::factory()->create();

    [, $foodVariant, $combVariant] = makeDualUseBxgy(
        $this->channel,
        $this->customerGroup,
        $this->currency,
        $food,
        $comb,
    );

    $cart = Cart::factory()->create([
        'channel_id' => $this->channel->id,
        'currency_id' => $this->currency->id,
    ]);

    $cart->lines()->create([
        'purchasable_type' => $foodVariant->getMorphClass(),
        'purchasable_id' => $foodVariant->id,
        'quantity' => 1,
    ]);

    $cart = $cart->calculate();
    $cart = $cart->refresh()->calculate();
    $cart = $cart->refresh()->calculate();

    $giftLines = $cart->lines->filter(
        fn ($line) => (int) $line->purchasable_id === (int) $combVariant->id
            && ! empty(data_get($line->meta, 'added_by_discount'))
    );

    expect($giftLines)->toHaveCount(1)
        ->and($giftLines->sum('quantity'))->toBe(1);
});

test('max_reward_qty is cart-wide for collection conditions across multiple products', function () {
    $collection = \Lunar\Models\Collection::factory()->create();

    $shoeA = Product::factory()->create();
    $shoeB = Product::factory()->create();
    $shoeC = Product::factory()->create();
    $patrol = Product::factory()->create();

    $shoeA->collections()->attach($collection->id);
    $shoeB->collections()->attach($collection->id);
    $shoeC->collections()->attach($collection->id);

    $variantA = ProductVariant::factory()->create([
        'product_id' => $shoeA->id,
        'purchasable' => 'always',
    ]);
    $variantB = ProductVariant::factory()->create([
        'product_id' => $shoeB->id,
        'purchasable' => 'always',
    ]);
    $variantC = ProductVariant::factory()->create([
        'product_id' => $shoeC->id,
        'purchasable' => 'always',
    ]);
    $patrolVariant = ProductVariant::factory()->create([
        'product_id' => $patrol->id,
        'purchasable' => 'always',
    ]);

    foreach ([$variantA, $variantB, $variantC] as $variant) {
        Price::factory()->create([
            'price' => 2000,
            'min_quantity' => 1,
            'currency_id' => $this->currency->id,
            'priceable_type' => $variant->getMorphClass(),
            'priceable_id' => $variant->id,
        ]);
    }

    Price::factory()->create([
        'price' => 500,
        'min_quantity' => 1,
        'currency_id' => $this->currency->id,
        'priceable_type' => $patrolVariant->getMorphClass(),
        'priceable_id' => $patrolVariant->id,
    ]);

    $discount = Discount::factory()->create([
        'type' => BuyXGetY::class,
        'name' => 'Collection gift cap',
        'coupon' => null,
        'starts_at' => now()->subMinute(),
        'data' => [
            'min_qty' => 1,
            'reward_qty' => 1,
            'max_reward_qty' => 4,
            'automatically_add_rewards' => true,
        ],
    ]);

    $discount->channels()->attach([
        $this->channel->id => ['enabled' => true, 'starts_at' => now()->subMinute()],
    ]);
    $discount->customerGroups()->attach([
        $this->customerGroup->id => ['enabled' => true, 'starts_at' => now()->subMinute()],
    ]);
    $discount->discountableConditions()->create([
        'discountable_type' => $collection->getMorphClass(),
        'discountable_id' => $collection->id,
        'type' => 'condition',
    ]);
    $discount->discountableRewards()->create([
        'discountable_type' => $patrol->getMorphClass(),
        'discountable_id' => $patrol->id,
        'type' => 'reward',
    ]);

    $cart = Cart::factory()->create([
        'channel_id' => $this->channel->id,
        'currency_id' => $this->currency->id,
    ]);

    $cart->lines()->create([
        'purchasable_type' => $variantA->getMorphClass(),
        'purchasable_id' => $variantA->id,
        'quantity' => 5,
    ]);
    $cart = $cart->calculate();

    expect($cart->lines
        ->filter(fn ($line) => (int) $line->purchasable_id === (int) $patrolVariant->id
            && ! empty(data_get($line->meta, 'added_by_discount')))
        ->sum('quantity'))->toBe(4);

    $cart->lines()->create([
        'purchasable_type' => $variantB->getMorphClass(),
        'purchasable_id' => $variantB->id,
        'quantity' => 2,
        'meta' => [
            'selected_gift_rewards' => [[
                'product_id' => (int) $patrol->id,
                'variant_id' => (int) $patrolVariant->id,
                'quantity' => 2,
            ]],
        ],
    ]);
    $cart = $cart->refresh()->calculate();

    $cart->lines()->create([
        'purchasable_type' => $variantC->getMorphClass(),
        'purchasable_id' => $variantC->id,
        'quantity' => 1,
        'meta' => [
            'selected_gift_rewards' => [[
                'product_id' => (int) $patrol->id,
                'variant_id' => (int) $patrolVariant->id,
                'quantity' => 1,
            ]],
        ],
    ]);
    $cart = $cart->refresh()->calculate();

    $giftQty = $cart->lines
        ->filter(fn ($line) => (int) $line->purchasable_id === (int) $patrolVariant->id
            && ! empty(data_get($line->meta, 'added_by_discount')))
        ->sum('quantity');

    expect($giftQty)->toBe(4)
        ->and($cart->lines->where('purchasable_id', $variantA->id)->sum('quantity'))->toBe(5)
        ->and($cart->lines->where('purchasable_id', $variantB->id)->sum('quantity'))->toBe(2)
        ->and($cart->lines->where('purchasable_id', $variantC->id)->sum('quantity'))->toBe(1);
});

test('max_reward_qty caps automatic gifts when condition quantity exceeds the cap', function () {
    $shoes = Product::factory()->create();
    $patrol = Product::factory()->create();

    [, $shoeVariant, $patrolVariant] = makeDualUseBxgy(
        $this->channel,
        $this->customerGroup,
        $this->currency,
        $shoes,
        $patrol,
        [
            'min_qty' => 1,
            'reward_qty' => 1,
            'max_reward_qty' => 4,
        ],
    );

    $cart = Cart::factory()->create([
        'channel_id' => $this->channel->id,
        'currency_id' => $this->currency->id,
    ]);

    $cart->lines()->create([
        'purchasable_type' => $shoeVariant->getMorphClass(),
        'purchasable_id' => $shoeVariant->id,
        'quantity' => 6,
    ]);

    $cart = $cart->calculate();

    $giftQty = $cart->lines
        ->filter(fn ($line) => (int) $line->purchasable_id === (int) $patrolVariant->id
            && ! empty(data_get($line->meta, 'added_by_discount')))
        ->sum('quantity');

    expect($giftQty)->toBe(4);
});

test('max_reward_qty trims gifts after cart quantity grows past the cap', function () {
    $shoes = Product::factory()->create();
    $patrol = Product::factory()->create();

    [, $shoeVariant, $patrolVariant] = makeDualUseBxgy(
        $this->channel,
        $this->customerGroup,
        $this->currency,
        $shoes,
        $patrol,
        [
            'min_qty' => 1,
            'reward_qty' => 1,
            'max_reward_qty' => 4,
        ],
    );

    $cart = Cart::factory()->create([
        'channel_id' => $this->channel->id,
        'currency_id' => $this->currency->id,
    ]);

    $parent = $cart->lines()->create([
        'purchasable_type' => $shoeVariant->getMorphClass(),
        'purchasable_id' => $shoeVariant->id,
        'quantity' => 3,
    ]);

    $cart = $cart->calculate();

    expect($cart->lines
        ->filter(fn ($line) => (int) $line->purchasable_id === (int) $patrolVariant->id
            && ! empty(data_get($line->meta, 'added_by_discount')))
        ->sum('quantity'))->toBe(3);

    $parent->update(['quantity' => 6]);
    $cart = $cart->refresh()->calculate();

    expect($cart->lines
        ->filter(fn ($line) => (int) $line->purchasable_id === (int) $patrolVariant->id
            && ! empty(data_get($line->meta, 'added_by_discount')))
        ->sum('quantity'))->toBe(4);
});
