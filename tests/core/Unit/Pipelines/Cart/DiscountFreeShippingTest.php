<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\DataTypes\Price as PriceDataType;
use Lunar\DataTypes\ShippingOption;
use Lunar\DiscountTypes\AdvancedAmountOff;
use Lunar\Facades\ShippingManifest;
use Lunar\Models\Cart;
use Lunar\Models\CartAddress;
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
use Lunar\Pipelines\Cart\ApplyDiscountFreeShipping;
use Lunar\Pipelines\Cart\ApplyDiscounts;
use Lunar\Pipelines\Cart\ApplyShipping;
use Lunar\Pipelines\Cart\Calculate;
use Lunar\Pipelines\Cart\CalculateLines;
use Lunar\Pipelines\Cart\CalculateShippingSubTotal;
use Lunar\Pipelines\Cart\CalculateTax;
use Lunar\Tests\Core\TestCase;

uses(TestCase::class);

uses(RefreshDatabase::class);

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

    $taxZone = TaxZone::factory()->create(['default' => true]);
    $taxRate = TaxRate::factory()->create(['tax_zone_id' => $taxZone->id]);
    $this->taxClass = TaxClass::factory()->create();

    TaxRateAmount::factory()->create([
        'percentage' => 20,
        'tax_class_id' => $this->taxClass->id,
        'tax_rate_id' => $taxRate->id,
    ]);

    foreach ([['COURIER', 1990, false], ['EXPRESS', 2990, false], ['PICKUP', 0, true]] as [$identifier, $price, $collect]) {
        ShippingManifest::addOption(new ShippingOption(
            name: $identifier,
            description: $identifier,
            identifier: $identifier,
            price: new PriceDataType($price, $this->currency, 1),
            taxClass: $this->taxClass,
            collect: $collect,
        ));
    }
});

/**
 * A cart with one 10.00 product line (20% tax) and the given shipping option.
 */
function freeShippingTestCart(?string $shippingOption = 'COURIER', ?string $coupon = null): Cart
{
    $cart = Cart::factory()->create([
        'currency_id' => test()->currency->id,
        'channel_id' => test()->channel->id,
        'coupon_code' => $coupon,
    ]);

    addFreeShippingTestLine($cart);

    CartAddress::factory()->create([
        'cart_id' => $cart->id,
        'type' => 'shipping',
        'shipping_option' => $shippingOption,
    ]);

    return $cart;
}

function addFreeShippingTestLine(Cart $cart, int $price = 1000): ProductVariant
{
    $variant = ProductVariant::factory()->create([
        'tax_class_id' => test()->taxClass->id,
    ]);

    Price::factory()->create([
        'price' => $price,
        'min_quantity' => 1,
        'currency_id' => $cart->currency_id,
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
    ]);

    $cart->lines()->create([
        'purchasable_type' => $variant->getMorphClass(),
        'purchasable_id' => $variant->id,
        'quantity' => 1,
    ]);

    return $variant;
}

/**
 * @param  array<string, mixed>  $data
 */
function freeShippingTestDiscount(?string $coupon = null, array $data = []): Discount
{
    $discount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => $coupon,
        'starts_at' => now()->subMinute(),
        'data' => array_merge([
            'fixed_value' => false,
            'percentage' => null,
            'free_shipping' => true,
        ], $data),
    ]);

    $discount->channels()->sync([
        test()->channel->id => ['enabled' => true, 'starts_at' => now()->subMinute()],
    ]);

    $discount->customerGroups()->sync([
        test()->customerGroup->id => ['enabled' => true, 'starts_at' => now()->subMinute()],
    ]);

    return $discount;
}

dataset('free shipping pipelines', [
    'default order' => [[
        CalculateLines::class,
        ApplyShipping::class,
        CalculateShippingSubTotal::class,
        ApplyDiscounts::class,
        ApplyDiscountFreeShipping::class,
        CalculateTax::class,
        Calculate::class,
    ]],
    'discounts before shipping' => [[
        CalculateLines::class,
        ApplyDiscounts::class,
        ApplyShipping::class,
        CalculateShippingSubTotal::class,
        ApplyDiscountFreeShipping::class,
        CalculateTax::class,
        Calculate::class,
    ]],
    'without shipping sub total stage' => [[
        CalculateLines::class,
        ApplyDiscounts::class,
        ApplyShipping::class,
        ApplyDiscountFreeShipping::class,
        CalculateTax::class,
        Calculate::class,
    ]],
]);

test('the default cart pipeline lists the free shipping stage directly before the tax stage', function () {
    $pipelines = config('lunar.cart.pipelines.cart');
    $stageIndex = array_search(ApplyDiscountFreeShipping::class, $pipelines, true);

    expect($stageIndex)->not->toBeFalse()
        ->and($pipelines[$stageIndex + 1] ?? null)->toBe(CalculateTax::class);
});

test('the tax stage does not waive shipping without the free shipping stage', function () {
    config(['lunar.cart.pipelines.cart' => [
        CalculateLines::class,
        ApplyDiscounts::class,
        ApplyShipping::class,
        CalculateShippingSubTotal::class,
        CalculateTax::class,
        Calculate::class,
    ]]);

    $discount = freeShippingTestDiscount('SHIPFREE');
    $cart = freeShippingTestCart(coupon: 'SHIPFREE')->calculate();

    // Products 10.00 + 20% tax, courier 19.90 + 20% tax.
    expect($cart->freeShippingDiscount?->id)->toBe($discount->id)
        ->and($cart->shippingDiscountTotal)->toBeNull()
        ->and($cart->shippingSubTotal->value)->toBe(1990)
        ->and($cart->shippingTotal->value)->toBe(2388)
        ->and($cart->total->value)->toBe(3588);
});

test('a free shipping discount waives the shipping in every pipeline order', function (array $pipelines, string $source) {
    config(['lunar.cart.pipelines.cart' => $pipelines]);

    $discount = freeShippingTestDiscount($source === 'coupon' ? 'SHIPFREE' : null);
    $cart = freeShippingTestCart(coupon: $source === 'coupon' ? 'SHIPFREE' : null);

    $cart = $cart->calculate();

    // Products 10.00 + 20% tax. The 19.90 courier is waived.
    expect($cart->freeShippingDiscount?->id)->toBe($discount->id)
        ->and($cart->shippingDiscountTotal?->value)->toBe(1990)
        ->and($cart->shippingSubTotal?->value)->toBe(0)
        ->and($cart->shippingTaxTotal?->value)->toBe(0)
        ->and($cart->shippingTotal?->value)->toBe(0)
        ->and($cart->shippingBreakdown->items->sum('price.value'))->toBe(0)
        ->and($cart->shippingAddress->shippingTotal?->value)->toBe(0)
        ->and($cart->taxTotal->value)->toBe(200)
        ->and($cart->total->value)->toBe(1200);
})->with('free shipping pipelines')->with(['coupon', 'automatic']);

test('a cart without a free shipping discount charges shipping', function () {
    $cart = freeShippingTestCart()->calculate();

    expect($cart->freeShippingDiscount)->toBeNull()
        ->and($cart->shippingDiscountTotal)->toBeNull()
        ->and($cart->shippingSubTotal->value)->toBe(1990)
        ->and($cart->shippingTotal->value)->toBe(2388)
        ->and($cart->total->value)->toBe(3588);
});

test('a changed shipping option is waived in the same request', function () {
    freeShippingTestDiscount('SHIPFREE');
    $cart = freeShippingTestCart(coupon: 'SHIPFREE')->calculate();

    $cart = $cart->setShippingOption(ShippingManifest::getOption($cart, 'EXPRESS'));

    expect($cart->shippingAddress->shipping_option)->toBe('EXPRESS')
        ->and($cart->shippingDiscountTotal?->value)->toBe(2990)
        ->and($cart->shippingTotal->value)->toBe(0)
        ->and($cart->total->value)->toBe(1200);
});

test('clearing the free shipping coupon charges shipping again', function () {
    freeShippingTestDiscount('SHIPFREE');
    $cart = freeShippingTestCart(coupon: 'SHIPFREE')->calculate();

    expect($cart->shippingTotal->value)->toBe(0);

    $cart->update(['coupon_code' => null]);
    $cart->recalculate();

    expect($cart->freeShippingDiscount)->toBeNull()
        ->and($cart->shippingDiscountTotal)->toBeNull()
        ->and($cart->shippingTotal->value)->toBe(2388)
        ->and($cart->total->value)->toBe(3588);
});

test('removing the only eligible line of an automatic free shipping discount charges shipping again', function () {
    $cart = freeShippingTestCart();
    $eligible = addFreeShippingTestLine($cart, 500);

    $discount = freeShippingTestDiscount();
    $discount->discountables()->create([
        'discountable_type' => Product::morphName(),
        'discountable_id' => $eligible->product_id,
        'type' => 'limitation',
    ]);

    $cart = $cart->calculate();

    expect($cart->freeShippingDiscount?->id)->toBe($discount->id)
        ->and($cart->shippingTotal->value)->toBe(0);

    $line = $cart->lines->firstWhere('purchasable_id', $eligible->id);
    $cart = $cart->remove($line->id);

    expect($cart->freeShippingDiscount)->toBeNull()
        ->and($cart->shippingDiscountTotal)->toBeNull()
        ->and($cart->shippingTotal->value)->toBe(2388)
        ->and($cart->total->value)->toBe(3588);
});

test('a free shipping discount reports no waived amount for a 0 priced pickup option', function () {
    $discount = freeShippingTestDiscount('SHIPFREE');
    $cart = freeShippingTestCart('PICKUP', 'SHIPFREE')->calculate();

    expect($cart->freeShippingDiscount?->id)->toBe($discount->id)
        ->and($cart->shippingDiscountTotal)->toBeNull()
        ->and($cart->shippingTotal->value)->toBe(0)
        ->and($cart->total->value)->toBe(1200);
});

test('a free shipping discount is reported before a shipping option is chosen', function () {
    $discount = freeShippingTestDiscount('SHIPFREE');
    $cart = freeShippingTestCart(null, 'SHIPFREE')->calculate();

    expect($cart->freeShippingDiscount?->id)->toBe($discount->id)
        ->and($cart->shippingDiscountTotal)->toBeNull()
        ->and($cart->shippingTotal)->toBeNull()
        ->and($cart->total->value)->toBe(1200);
});

test('an explicit shipping override keeps its price and the coupon keeps its product discount', function () {
    $discount = freeShippingTestDiscount('SHIP10', ['percentage' => 10]);
    $cart = freeShippingTestCart(coupon: 'SHIP10');

    $cart->shippingOptionOverride = new ShippingOption(
        name: 'Split',
        description: 'Split',
        identifier: 'COURIER',
        price: new PriceDataType(1200, $this->currency, 1),
        taxClass: $this->taxClass,
    );

    $cart = $cart->calculate();

    // Products 10.00 - 10% = 9.00 + 20% tax, shipping 12.00 + 20% tax.
    expect($cart->freeShippingDiscount?->id)->toBe($discount->id)
        ->and($cart->shippingDiscountTotal)->toBeNull()
        ->and($cart->lines->first()->subTotalDiscounted->value)->toBe(900)
        ->and($cart->shippingTotal->value)->toBe(1440)
        ->and($cart->total->value)->toBe(2520);
});

test('a free shipping discount left from an earlier calculation is cleared', function () {
    $cart = freeShippingTestCart();

    $cart->freeShippingDiscount = freeShippingTestDiscount('UNUSED');
    $cart->shippingDiscountTotal = new PriceDataType(1990, $this->currency, 1);

    $cart->recalculate();

    expect($cart->freeShippingDiscount)->toBeNull()
        ->and($cart->shippingDiscountTotal)->toBeNull()
        ->and($cart->shippingTotal->value)->toBe(2388);
});

test('the free shipping discount and waived amount are restored from the request cache', function () {
    $discount = freeShippingTestDiscount('SHIPFREE');
    $cart = freeShippingTestCart(coupon: 'SHIPFREE')->calculate();

    $restored = Cart::find($cart->id);

    expect($restored->freeShippingDiscount?->id)->toBe($discount->id)
        ->and($restored->shippingDiscountTotal?->value)->toBe(1990);
});
