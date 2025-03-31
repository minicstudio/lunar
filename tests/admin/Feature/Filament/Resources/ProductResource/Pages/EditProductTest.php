<?php

use Livewire\Livewire;

uses(\Lunar\Tests\Admin\Unit\Filament\TestCase::class)
    ->group('resource.product');

it('can edit variant attributes', function () {
    \Lunar\Models\CustomerGroup::factory()->create([
        'default' => true,
    ]);

    \Lunar\Models\Language::factory()->create([
        'default' => true,
    ]);

    $product = \Lunar\Models\Product::factory()->create();
    $variant = \Lunar\Models\ProductVariant::factory()->create([
        'product_id' => $product->id,
    ]);

    $group = \Lunar\Models\AttributeGroup::factory()->create([
        'attributable_type' => 'product_variant',
        'name' => [
            'en' => 'Variant Details',
        ],
        'handle' => 'variant_details',
        'position' => 1,
    ]);

    $attribute = \Lunar\Models\Attribute::factory()->create([
        'attribute_type' => 'product_variant',
        'attribute_group_id' => $group->id,
        'position' => 1,
        'name' => [
            'en' => 'Test Attribute',
        ],
        'handle' => 'test-attribute',
        'section' => 'main',
        'required' => false,
        'system' => false,
        'searchable' => false,
    ]);

    \Illuminate\Support\Facades\DB::table('lunar_attributables')->insert([
        'attribute_id' => $attribute->id,
        'attributable_type' => 'product_type',
        'attributable_id' => $product->productType->id,
    ]);

    $this->asStaff(admin: true);

    $component = Livewire::test(\Lunar\Admin\Filament\Resources\ProductResource\Pages\EditProduct::class, [
        'record' => $product->id,
        'pageClass' => 'productEdit',
    ])->assertSuccessful();

    expect($variant->attr($attribute->handle))->toBeNull();

    $component->set('data.variant.attribute_data.'.$attribute->handle, 'Hello');
    $component->call('save');

    expect($variant->refresh()->attr($attribute->handle))->toBe('Hello');
});

it('can save attributes', function () {
    \Lunar\Models\Language::factory()->create([
        'default' => true,
    ]);

    \Lunar\Models\TaxClass::factory()->create([
        'default' => true,
    ]);

    $record = \Lunar\Models\Product::factory()->create();
    \Lunar\Models\ProductVariant::factory()->create([
        'product_id' => $record->id,
    ]);

    $group = \Lunar\Models\AttributeGroup::factory()->create([
        'attributable_type' => 'product',
        'name' => [
            'en' => 'Details',
        ],
        'handle' => 'details',
        'position' => 1,
    ]);

    $attribute = \Lunar\Models\Attribute::factory()->create([
        'attribute_type' => 'product',
        'attribute_group_id' => $group->id,
        'position' => 1,
        'name' => [
            'en' => 'Name',
        ],
        'handle' => 'name',
        'section' => 'main',
        'required' => false,
        'system' => false,
        'searchable' => false,
    ]);

    \Illuminate\Support\Facades\DB::table('lunar_attributables')->insert([
        'attribute_id' => $attribute->id,
        'attributable_type' => 'product_type',
        'attributable_id' => $record->productType->id,
    ]);

    $this->asStaff(admin: true);

    \Livewire\Livewire::test(\Lunar\Admin\Filament\Resources\ProductResource\Pages\EditProduct::class, [
        'record' => $record->getRouteKey(),
        'pageClass' => 'productEdit',
    ])->fillForm([
        'attribute_data' => [
            'name' => new \Lunar\FieldTypes\Text('New Product Name'),
        ],
    ])->call('save')->assertHasNoFormErrors();

    expect($record->refresh()->attr('name'))->toBe('New Product Name');
});
