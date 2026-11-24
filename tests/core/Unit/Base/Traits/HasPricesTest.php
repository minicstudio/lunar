<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Models\Currency;
use Lunar\Models\Price;
use Lunar\Models\ProductVariant;
use Lunar\Tests\Core\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

test('canonical base price prefers the newest row for the default currency', function () {
    $defaultCurrency = Currency::factory()->create([
        'default' => true,
        'code' => 'GBP',
        'decimal_places' => 2,
    ]);

    $otherCurrency = Currency::factory()->create([
        'default' => false,
        'code' => 'EUR',
        'decimal_places' => 2,
    ]);

    $variant = ProductVariant::factory()->create();

    $variant->prices()->create([
        'price' => 239222,
        'currency_id' => $defaultCurrency->id,
        'min_quantity' => 1,
        'customer_group_id' => null,
    ]);

    $variant->prices()->create([
        'price' => 9999,
        'currency_id' => $otherCurrency->id,
        'min_quantity' => 1,
        'customer_group_id' => null,
    ]);

    $canonical = $variant->prices()->create([
        'price' => 15000,
        'currency_id' => $defaultCurrency->id,
        'min_quantity' => 1,
        'customer_group_id' => null,
    ]);

    expect($variant->fresh()->canonicalBasePrice()?->id)->toBe($canonical->id)
        ->and($variant->fresh()->canonicalBasePrice()?->price->value)->toBe(15000);
});

test('deduplicate base prices keeps the chosen row for a currency', function () {
    $currency = Currency::factory()->create([
        'default' => true,
        'decimal_places' => 2,
    ]);

    $variant = ProductVariant::factory()->create();

    $old = $variant->prices()->create([
        'price' => 239222,
        'currency_id' => $currency->id,
        'min_quantity' => 1,
        'customer_group_id' => null,
    ]);

    $keep = $variant->prices()->create([
        'price' => 15000,
        'currency_id' => $currency->id,
        'min_quantity' => 1,
        'customer_group_id' => null,
    ]);

    $variant->deduplicateBasePricesForCurrency($currency->id, $keep->id);

    expect($variant->basePrices()->where('currency_id', $currency->id)->pluck('id')->all())
        ->toBe([$keep->id]);

    $this->assertDatabaseMissing((new Price)->getTable(), [
        'id' => $old->id,
    ]);
});
