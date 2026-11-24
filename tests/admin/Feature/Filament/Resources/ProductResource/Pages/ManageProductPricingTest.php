<?php

use Livewire\Livewire;
use Lunar\Admin\Filament\Resources\ProductResource;
use Lunar\Admin\Filament\Resources\ProductResource\Pages\ManageProductPricing;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Lunar\Tests\Admin\Feature\Filament\TestCase;

uses(TestCase::class)
    ->group('resource.product');

it('can render product prices create page', function () {
    Language::factory()->create([
        'default' => true,
    ]);

    Currency::factory()->create([
        'default' => true,
    ]);

    $record = Product::factory()->create();

    ProductVariant::factory()->create([
        'product_id' => $record->id,
    ]);

    $this->asStaff(admin: true)
        ->get(ProductResource::getUrl('pricing', [
            'record' => $record,
        ]))
        ->assertSuccessful();
});

it('will show in navigation when only one variant exists', function () {
    Language::factory()->create([
        'default' => true,
    ]);

    Currency::factory()->create([
        'default' => true,
    ]);

    $record = Product::factory()->create();

    ProductVariant::factory()->create([
        'product_id' => $record->id,
    ]);

    $this->asStaff(admin: true)
        ->get(ProductResource::getUrl('edit', [
            'record' => $record,
        ]))
        ->assertSuccessful()
        ->assertSeeText(
            __('lunarpanel::relationmanagers.pricing.title')
        );
});

it('will not show in navigation when multiple variants exist', function () {
    Language::factory()->create([
        'default' => true,
    ]);

    Currency::factory()->create([
        'default' => true,
    ]);

    $record = Product::factory()->create();

    ProductVariant::factory(2)->create([
        'product_id' => $record->id,
    ]);

    $this->asStaff(admin: true)
        ->get(ProductResource::getUrl('index', [
            'record' => $record,
        ]))
        ->assertSuccessful()
        ->assertDontSeeText(
            __('lunarpanel::relationmanagers.pricing.title')
        );
});

it('can set product base prices correctly', function () {
    Language::factory()->create([
        'default' => true,
    ]);

    Currency::factory()->create([
        'default' => true,
    ]);

    $record = Product::factory()->create();

    $variant = ProductVariant::factory()->create([
        'product_id' => $record->id,
    ]);

    $this->asStaff(admin: true);

    Livewire::test(ManageProductPricing::class, [
        'record' => $record->id,
        'pageClass' => 'productPriceRelationManager',
    ])->set('basePrices', [
        [
            'id' => null,
            'currency_id' => Currency::getDefault()->id,
            'sync_prices' => false,
            'label' => 'GBP',
            'value' => '2.32',
            'factor' => '100',
        ],
    ])->call('save')->assertHasNoErrors();

    \Pest\Laravel\assertDatabaseHas((new Price)->getTable(), [
        'price' => '232',
    ]);

});

it('deduplicates base prices for a currency when saving pricing', function () {
    Language::factory()->create([
        'default' => true,
    ]);

    $currency = Currency::factory()->create([
        'default' => true,
        'decimal_places' => 2,
    ]);

    $record = Product::factory()->create();

    $variant = ProductVariant::factory()->create([
        'product_id' => $record->id,
    ]);

    $variant->prices()->create([
        'price' => 239222,
        'currency_id' => $currency->id,
        'min_quantity' => 1,
        'customer_group_id' => null,
    ]);

    $canonical = $variant->prices()->create([
        'price' => 15000,
        'currency_id' => $currency->id,
        'min_quantity' => 1,
        'customer_group_id' => null,
    ]);

    $this->asStaff(admin: true);

    Livewire::test(ManageProductPricing::class, [
        'record' => $record->id,
        'pageClass' => 'productPriceRelationManager',
    ])->set('basePrices', [
        [
            'id' => $canonical->id,
            'currency_id' => $currency->id,
            'sync_prices' => false,
            'label' => $currency->code,
            'value' => '175',
            'original_value' => '150',
            'compare_price' => '0',
            'original_compare_price' => '0',
            'factor' => '100',
        ],
    ])->call('save')->assertHasNoErrors();

    $variant->refresh();

    expect($variant->basePrices)->toHaveCount(1)
        ->and($variant->canonicalBasePrice()?->id)->toBe($canonical->id)
        ->and($variant->canonicalBasePrice()?->price->value)->toBe(17500);
});
