<?php

use Livewire\Livewire;
use Lunar\Admin\Filament\Resources\ProductResource\Pages\ListProducts;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Attribute;
use Lunar\Models\Collection;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Lunar\Tests\Admin\Feature\Filament\TestCase;

uses(TestCase::class)
    ->group('resource.product');

beforeEach(function () {
    Language::factory()->create([
        'default' => true,
    ]);
    Currency::factory()->create([
        'default' => true,
        'decimal_places' => 2,
    ]);
});

it('can create product', function () {
    Attribute::factory()->create([
        'type' => TranslatedText::class,
        'attribute_type' => 'product',
        'handle' => 'name',
        'name' => [
            'en' => 'Name',
        ],
        'description' => [
            'en' => 'Description',
        ],
    ]);
    TaxClass::factory()->create([
        'default' => true,
    ]);
    $language = Language::getDefault();

    $productType = ProductType::factory()->create();

    $this->asStaff();

    Livewire::test(ListProducts::class)
        ->callAction('create', data: [
            'name' => [$language->code => 'Foo Bar'],
            'base_price' => 10.99,
            'sku' => 'ABCABCAB',
            'product_type_id' => $productType->id,
        ])->assertHasNoActionErrors();

    \Pest\Laravel\assertDatabaseHas((new Product)->getTable(), [
        'product_type_id' => $productType->id,
        'status' => 'draft',
        'attribute_data' => json_encode([
            'name' => [
                'field_type' => TranslatedText::class,
                'value' => [
                    $language->code => 'Foo Bar',
                ],
            ],
        ]),
    ]);

    $this->assertDatabaseHas((new ProductVariant)->getTable(), [
        'sku' => 'ABCABCAB',
    ]);

    $this->assertDatabaseHas((new Price)->getTable(), [
        'price' => '1099',
    ]);
});

it('can filter products by product type', function () {
    $this->asStaff();

    $typeA = ProductType::factory()->create();
    $typeB = ProductType::factory()->create();

    $productA = Product::factory()->create(['product_type_id' => $typeA->id]);
    $productB = Product::factory()->create(['product_type_id' => $typeB->id]);

    Livewire::test(ListProducts::class)
        ->call('loadTable')
        ->filterTable('productType', $typeA->id)
        ->assertCanSeeTableRecords([$productA])
        ->assertCanNotSeeTableRecords([$productB]);
});

it('can filter products by collections', function () {
    $this->asStaff();

    $collection = Collection::factory()->create();

    $inCollection = Product::factory()->create();
    $notInCollection = Product::factory()->create();

    $inCollection->collections()->attach($collection);

    Livewire::test(ListProducts::class)
        ->call('loadTable')
        ->filterTable('collections', [$collection->id])
        ->assertCanSeeTableRecords([$inCollection])
        ->assertCanNotSeeTableRecords([$notInCollection]);
});
