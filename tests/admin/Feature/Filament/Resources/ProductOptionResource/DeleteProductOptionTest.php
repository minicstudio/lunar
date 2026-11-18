<?php

use Filament\Actions\DeleteAction;
use Livewire\Livewire;
use Lunar\Admin\Filament\Resources\ProductOptionResource\Pages\EditProductOption;
use Lunar\Admin\Filament\Resources\ProductOptionResource\Pages\ListProductOptions;
use Lunar\Admin\Filament\Resources\ProductOptionResource\RelationManagers\ValuesRelationManager;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\ProductOption;
use Lunar\Models\ProductOptionValue;
use Lunar\Models\ProductVariant;
use Lunar\Tests\Admin\Feature\Filament\TestCase;

uses(TestCase::class)
    ->group('resource.product-option');

beforeEach(function () {
    Language::factory()->create([
        'default' => true,
        'code' => 'en',
    ]);

    $this->asStaff(admin: true);
});

it('refuses to delete a product option value that is used by a product', function () {
    $option = ProductOption::factory()->create();
    $value = ProductOptionValue::factory()->create([
        'product_option_id' => $option->id,
        'name' => ['en' => 'Red'],
    ]);
    $variant = ProductVariant::factory()->create();
    $variant->values()->attach($value->id);

    Livewire::test(ValuesRelationManager::class, [
        'ownerRecord' => $option,
        'pageClass' => EditProductOption::class,
    ])
        ->callTableAction(DeleteAction::class, $value)
        ->assertNotified(__('lunarpanel::productoption.values.delete.error.title'));

    $this->assertModelExists($value);
});

it('deletes a product option value that is not used by a product', function () {
    $option = ProductOption::factory()->create();
    $value = ProductOptionValue::factory()->create([
        'product_option_id' => $option->id,
        'name' => ['en' => 'Red'],
    ]);

    Livewire::test(ValuesRelationManager::class, [
        'ownerRecord' => $option,
        'pageClass' => EditProductOption::class,
    ])
        ->callTableAction(DeleteAction::class, $value);

    $this->assertModelMissing($value);
});

it('refuses to bulk delete product option values when one is used by a product', function () {
    $option = ProductOption::factory()->create();
    $unused = ProductOptionValue::factory()->create([
        'product_option_id' => $option->id,
    ]);
    $used = ProductOptionValue::factory()->create([
        'product_option_id' => $option->id,
    ]);
    $variant = ProductVariant::factory()->create();
    $variant->values()->attach($used->id);

    Livewire::test(ValuesRelationManager::class, [
        'ownerRecord' => $option,
        'pageClass' => EditProductOption::class,
    ])
        ->callTableBulkAction('delete', [$unused, $used])
        ->assertNotified(__('lunarpanel::productoption.values.delete.error.title'));

    $this->assertModelExists($unused);
    $this->assertModelExists($used);
});

it('refuses to delete a product option that is attached to a product', function () {
    $option = ProductOption::factory()->create();
    $product = Product::factory()->create();
    $product->productOptions()->attach($option->id, ['position' => 1]);

    Livewire::test(EditProductOption::class, [
        'record' => $option->getRouteKey(),
    ])
        ->callAction(DeleteAction::class)
        ->assertNotified(__('lunarpanel::productoption.delete.error.title'));

    $this->assertModelExists($option);
});

it('refuses to delete a product option whose value is used by a variant', function () {
    $option = ProductOption::factory()->create();
    $value = ProductOptionValue::factory()->create([
        'product_option_id' => $option->id,
    ]);
    $variant = ProductVariant::factory()->create();
    $variant->values()->attach($value->id);

    Livewire::test(EditProductOption::class, [
        'record' => $option->getRouteKey(),
    ])
        ->callAction(DeleteAction::class)
        ->assertNotified(__('lunarpanel::productoption.delete.error.title'));

    $this->assertModelExists($option);
    $this->assertModelExists($value);
});

it('deletes a product option value when every connected product is soft deleted', function () {
    $option = ProductOption::factory()->create();
    $value = ProductOptionValue::factory()->create([
        'product_option_id' => $option->id,
        'name' => ['en' => 'Red'],
    ]);
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
    ]);
    $variant->values()->attach($value->id);
    $product->delete();

    Livewire::test(ValuesRelationManager::class, [
        'ownerRecord' => $option,
        'pageClass' => EditProductOption::class,
    ])
        ->callTableAction(DeleteAction::class, $value);

    $this->assertModelMissing($value);
});

it('deletes a product option when every connected product is soft deleted', function () {
    $option = ProductOption::factory()->create();
    $value = ProductOptionValue::factory()->create([
        'product_option_id' => $option->id,
    ]);
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
    ]);
    $product->productOptions()->attach($option->id, ['position' => 1]);
    $variant->values()->attach($value->id);
    $product->delete();

    Livewire::test(EditProductOption::class, [
        'record' => $option->getRouteKey(),
    ])->callAction(DeleteAction::class);

    $this->assertModelMissing($option);
    $this->assertModelMissing($value);
});

it('refuses to delete a product option while a live product remains beside a soft deleted one', function () {
    $option = ProductOption::factory()->create();
    $liveProduct = Product::factory()->create();
    $trashedProduct = Product::factory()->create();
    $liveProduct->productOptions()->attach($option->id, ['position' => 1]);
    $trashedProduct->productOptions()->attach($option->id, ['position' => 2]);
    $trashedProduct->delete();

    Livewire::test(EditProductOption::class, [
        'record' => $option->getRouteKey(),
    ])
        ->callAction(DeleteAction::class)
        ->assertNotified(__('lunarpanel::productoption.delete.error.title'));

    $this->assertModelExists($option);
});

it('deletes a product option that is not used by a product', function () {
    $option = ProductOption::factory()->create();
    ProductOptionValue::factory()->create([
        'product_option_id' => $option->id,
    ]);

    Livewire::test(EditProductOption::class, [
        'record' => $option->getRouteKey(),
    ])->callAction(DeleteAction::class);

    $this->assertModelMissing($option);
    $this->assertDatabaseCount((new ProductOptionValue)->getTable(), 0);
});

it('refuses to bulk delete product options that are attached to products', function () {
    $linked = ProductOption::factory()->create();
    $free = ProductOption::factory()->create();
    $product = Product::factory()->create();
    $product->productOptions()->attach($linked->id, ['position' => 1]);

    Livewire::test(ListProductOptions::class)
        ->callTableBulkAction('delete', [$linked, $free])
        ->assertNotified(__('lunarpanel::productoption.delete.error.title'));

    $this->assertModelExists($linked);
    $this->assertModelExists($free);
});
