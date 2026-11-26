<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\FieldTypes\File;
use Lunar\FieldTypes\Number;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Attribute;
use Lunar\Models\AttributeGroup;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\ProductOption;
use Lunar\Models\ProductType;
use Lunar\ProductImport\Support\TargetFieldRegistry;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Language::factory()->create(['code' => 'ro', 'default' => true]);
    Language::factory()->create(['code' => 'hu', 'default' => false]);
    Currency::factory()->create(['code' => 'RON', 'default' => true, 'decimal_places' => 2]);

    $this->productType = ProductType::factory()->create();

    $this->material = $material = Attribute::factory()->create(['handle' => 'material', 'type' => TranslatedText::class, 'name' => ['en' => 'Material']]);
    $manual = Attribute::factory()->create(['handle' => 'manual', 'type' => File::class, 'name' => ['en' => 'Manual']]);
    $weightClass = Attribute::factory()->create(['handle' => 'clasa', 'type' => Number::class, 'name' => ['en' => 'Clasa']]);
    $name = Attribute::factory()->create(['handle' => 'name', 'type' => TranslatedText::class, 'name' => ['en' => 'Name']]);

    $this->productType->mappedAttributes()->attach([$material->id, $manual->id, $weightClass->id, $name->id]);
});

test('a translated text attribute is offered with a language and a file attribute is not', function () {
    $registry = app(TargetFieldRegistry::class);

    expect($registry->exists('attribute.material', $this->productType->id))->toBeTrue()
        ->and($registry->takesLocale('attribute.material', $this->productType->id))->toBeTrue()
        ->and($registry->label('attribute.material', $this->productType->id))->toBe($this->material->attributeGroup->translate('name').' › Material')
        ->and($registry->exists('attribute.clasa', $this->productType->id))->toBeTrue()
        ->and($registry->takesLocale('attribute.clasa', $this->productType->id))->toBeFalse()
        ->and($registry->exists('attribute.manual', $this->productType->id))->toBeFalse()
        ->and($registry->exists('attribute.name', $this->productType->id))->toBeFalse()
        ->and($registry->exists('attribute.material', null))->toBeFalse();
});

test('the fixed targets are always offered and name and description take a language', function () {
    $codes = app(TargetFieldRegistry::class)->targetCodes($this->productType->id);

    expect($codes)->toContain(
        'product.name', 'product.description', 'product.status', 'product.brand', 'product.category', 'variant.sku', 'variant.ean',
        'variant.price', 'variant.stock', 'variant.weight', 'variant.option', 'group_key', 'ignore',
    )->and(app(TargetFieldRegistry::class)->takesLocale('product.name', null))->toBeTrue()
        ->and(app(TargetFieldRegistry::class)->takesLocale('variant.sku', null))->toBeFalse();
});

test('the store languages are listed with the default first', function () {
    expect(app(TargetFieldRegistry::class)->languageCodes())->toBe(['ro', 'hu'])
        ->and(app(TargetFieldRegistry::class)->defaultLanguage())->toBe('ro');
});

test('existing product options are listed', function () {
    $size = ProductOption::factory()->create(['name' => ['en' => 'Marime'], 'handle' => 'marime']);
    $color = ProductOption::factory()->create(['name' => ['en' => 'Szín'], 'handle' => 'szin']);

    expect(app(TargetFieldRegistry::class)->options())->toBe([$size->id => 'Marime', $color->id => 'Szín']);
});

test('the price label says whether prices are stored with tax', function (bool $inclusive, string $label) {
    config(['lunar.pricing.stored_inclusive_of_tax' => $inclusive]);

    expect(app(TargetFieldRegistry::class)->label('variant.price'))->toBe($label);
})->with([
    'incl. tax' => [true, 'Variant price (RON, incl. tax)'],
    'excl. tax' => [false, 'Variant price (RON, excl. tax)'],
]);

test('the new product, variant, option, and structure targets are offered with their labels', function () {
    $registry = app(TargetFieldRegistry::class);

    expect($registry->targetCodes($this->productType->id, 'generic'))->toContain(
        'product.tags', 'product.properties', 'product.images', 'product.upsells', 'product.cross_sells',
        'variant.gtin', 'variant.mpn', 'variant.price_impact', 'variant.sale_price', 'variant.sale_starts', 'variant.sale_ends',
        'variant.discount_amount', 'variant.discount_percent', 'variant.stock_status', 'variant.backorders', 'variant.min_quantity',
        'variant.shippable', 'variant.weight_impact', 'variant.length', 'variant.width', 'variant.height', 'variant.image',
        'variant.option_name', 'variant.option_value', 'variant.options', 'source.id', 'source.parent',
    )->and($registry->label('product.tags'))->toBe('Tags')
        ->and($registry->label('variant.gtin'))->toBe('Variant GTIN')
        ->and($registry->label('variant.sale_ends'))->toBe('Sale end')
        ->and($registry->label('variant.option_name'))->toBe('Option name (pair)')
        ->and($registry->label('source.parent'))->toBe('Parent reference')
        ->and($registry->format('variant.sale_price', null))->toBe('number')
        ->and($registry->format('variant.sale_starts', null))->toBe('date')
        ->and($registry->format('variant.stock_status', null))->toBeNull();
});

test('the row type is offered on every sheet without any other setting', function () {
    $registry = app(TargetFieldRegistry::class);

    expect($registry->targetCodes($this->productType->id))->toContain('source.type', 'source.id', 'source.parent')
        ->and($registry->targetCodes(null))->toContain('source.type')
        ->and($registry->exists('source.type', $this->productType->id))->toBeTrue()
        ->and($registry->label('source.type'))->toBe('Row type');
});

test('an attribute target is labelled with its attribute group, and the url slug takes a language', function () {
    $seo = AttributeGroup::factory()->create(['handle' => 'seo', 'name' => ['en' => 'Search Engine Optimization']]);
    $title = Attribute::factory()->create([
        'handle' => 'product-meta-title', 'type' => TranslatedText::class, 'name' => ['en' => 'Title'], 'attribute_group_id' => $seo->id,
    ]);
    $this->productType->mappedAttributes()->attach($title->id);

    $registry = app(TargetFieldRegistry::class);
    $definition = collect($registry->targets($this->productType->id))->firstWhere('target', 'attribute.product-meta-title');

    expect($definition->label)->toBe('Search Engine Optimization › Title')
        ->and($definition->takesLocale)->toBeTrue()
        ->and($registry->exists('product.slug', null))->toBeTrue()
        ->and($registry->takesLocale('product.slug', null))->toBeTrue()
        ->and($registry->label('product.slug'))->toBe('URL slug');
});
