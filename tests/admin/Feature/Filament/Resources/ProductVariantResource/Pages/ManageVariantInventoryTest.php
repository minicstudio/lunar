<?php

use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Lunar\Admin\Events\ProductVariantInventoryUpdated;
use Lunar\Admin\Filament\Resources\ProductVariantResource;
use Lunar\Admin\Filament\Resources\ProductVariantResource\Pages\ManageVariantInventory;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Lunar\Tests\Admin\Feature\Filament\TestCase;

uses(TestCase::class)
    ->group('resource.product-variant');

it('dispatches ProductVariantInventoryUpdated event when updating variant stock figures', function () {
    Event::fake([ProductVariantInventoryUpdated::class]);

    Language::factory()->create([
        'default' => true,
    ]);

    Currency::factory()->create([
        'default' => true,
        'decimal_places' => 2,
    ]);

    $product = Product::factory()->create();

    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
    ]);

    $this->asStaff();

    Livewire::test(
        ManageVariantInventory::class, [
            'record' => $variant->getRouteKey(),
        ])->fillForm([
            'stock' => 250,
            'backorder' => 25,
            'purchasable' => 'in_stock_or_on_backorder',
        ])->call('save')->assertHasNoErrors();

    Event::assertDispatched(ProductVariantInventoryUpdated::class, function ($event) use ($variant) {
        return $event->model->is($variant)
            && $event->model->stock == 250
            && $event->model->backorder == 25;
    });

    $this->assertDatabaseHas((new ProductVariant)->getTable(), [
        'id' => $variant->id,
        'stock' => 250,
        'backorder' => 25,
        'purchasable' => 'in_stock_or_on_backorder',
    ]);
});

it('includes preorder in the purchasability options', function () {
    $options = ProductVariantResource::getPurchasableFormComponent()->getOptions();

    expect($options)->toHaveKey('preorder')
        ->and($options['preorder'])->toBe('Preorder');
});

it('shows the unused value helper when purchasability is preorder', function () {
    Language::factory()->create([
        'default' => true,
    ]);

    Currency::factory()->create([
        'default' => true,
    ]);

    $product = Product::factory()->create();

    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'purchasable' => 'preorder',
    ]);

    $this->asStaff();

    $helper = __('lunarpanel::productvariant.form.purchasable.unused_helper');

    $html = Livewire::test(ManageVariantInventory::class, [
        'record' => $variant->getRouteKey(),
    ])->assertSee($helper)
        ->assertSee(__('lunarpanel::productvariant.form.stock.label'))
        ->assertSee(__('lunarpanel::productvariant.form.backorder.label'))
        ->html();

    expect(substr_count($html, $helper))->toBe(2);
});

it('hides the unused value helper when purchasability is in stock', function () {
    Language::factory()->create([
        'default' => true,
    ]);

    Currency::factory()->create([
        'default' => true,
    ]);

    $product = Product::factory()->create();

    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'purchasable' => 'in_stock',
    ]);

    $this->asStaff();

    Livewire::test(ManageVariantInventory::class, [
        'record' => $variant->getRouteKey(),
    ])->assertDontSee(__('lunarpanel::productvariant.form.purchasable.unused_helper'))
        ->assertSee(__('lunarpanel::productvariant.form.stock.label'))
        ->assertSee(__('lunarpanel::productvariant.form.backorder.label'));
});

it('saves preorder purchasability and can replace it with in stock', function () {
    Language::factory()->create([
        'default' => true,
    ]);

    Currency::factory()->create([
        'default' => true,
        'decimal_places' => 2,
    ]);

    $product = Product::factory()->create();

    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
    ]);

    $this->asStaff();

    $component = Livewire::test(ManageVariantInventory::class, [
        'record' => $variant->getRouteKey(),
    ]);

    $component->fillForm([
        'purchasable' => 'preorder',
    ])->call('save')->assertHasNoErrors();

    $this->assertDatabaseHas((new ProductVariant)->getTable(), [
        'id' => $variant->id,
        'purchasable' => 'preorder',
    ]);

    $component->fillForm([
        'purchasable' => 'in_stock',
    ])->call('save')->assertHasNoErrors();

    $this->assertDatabaseHas((new ProductVariant)->getTable(), [
        'id' => $variant->id,
        'purchasable' => 'in_stock',
    ]);
});

it('rejects a unit quantity below one', function () {
    Language::factory()->create(['default' => true]);
    Currency::factory()->create(['default' => true]);

    $product = Product::factory()->create();

    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
    ]);

    $this->asStaff();

    Livewire::test(
        ManageVariantInventory::class, [
            'record' => $variant->getRouteKey(),
        ])->fillForm([
            'unit_quantity' => 0,
        ])->call('save')
        ->assertHasFormErrors(['unit_quantity' => 'min']);
});

it('rejects a quantity increment that is not a multiple of the unit quantity', function () {
    Language::factory()->create(['default' => true]);
    Currency::factory()->create(['default' => true]);

    $product = Product::factory()->create();

    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'unit_quantity' => 4,
        'quantity_increment' => 4,
    ]);

    $this->asStaff();

    Livewire::test(
        ManageVariantInventory::class, [
            'record' => $variant->getRouteKey(),
        ])->fillForm([
            'unit_quantity' => 4,
            'quantity_increment' => 6,
        ])->call('save')
        ->assertHasFormErrors(['quantity_increment']);
});

it('accepts a quantity increment that is a multiple of the unit quantity', function () {
    Language::factory()->create(['default' => true]);
    Currency::factory()->create(['default' => true]);

    $product = Product::factory()->create();

    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'unit_quantity' => 4,
        'quantity_increment' => 4,
    ]);

    $this->asStaff();

    Livewire::test(
        ManageVariantInventory::class, [
            'record' => $variant->getRouteKey(),
        ])->fillForm([
            'unit_quantity' => 4,
            'quantity_increment' => 8,
            'min_quantity' => 8,
        ])->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas((new ProductVariant)->getTable(), [
        'id' => $variant->id,
        'unit_quantity' => 4,
        'quantity_increment' => 8,
    ]);
});

it('snaps quantity increment to unit quantity when unit quantity changes and increment is invalid', function () {
    Language::factory()->create(['default' => true]);
    Currency::factory()->create(['default' => true]);

    $product = Product::factory()->create();

    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'unit_quantity' => 1,
        'quantity_increment' => 1,
    ]);

    $this->asStaff();

    Livewire::test(
        ManageVariantInventory::class, [
            'record' => $variant->getRouteKey(),
        ])->fillForm([
            'unit_quantity' => 4,
        ])->assertFormSet([
            'quantity_increment' => 4,
        ]);
});
