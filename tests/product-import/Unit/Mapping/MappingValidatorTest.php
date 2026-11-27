<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\ProductOption;
use Lunar\Models\ProductType;
use Lunar\ProductImport\Data\ColumnFormat;
use Lunar\ProductImport\Data\ColumnMapping;
use Lunar\ProductImport\Data\Grouping;
use Lunar\ProductImport\Data\ImportOptions;
use Lunar\ProductImport\Data\SheetMapping;
use Lunar\ProductImport\Enums\ExistingVariants;
use Lunar\ProductImport\Enums\GroupingStrategy;
use Lunar\ProductImport\Enums\MatchKey;
use Lunar\ProductImport\Enums\NewProductStatus;
use Lunar\ProductImport\Exceptions\InvalidMapping;
use Lunar\ProductImport\Mapping\MappingValidator;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Language::factory()->create(['code' => 'ro', 'default' => true]);
    Language::factory()->create(['code' => 'hu', 'default' => false]);
    Currency::factory()->create(['code' => 'RON', 'default' => true, 'decimal_places' => 2]);

    $this->productType = ProductType::factory()->create();
});

/**
 * A mapping for the test product type with the given columns.
 *
 * @param  list<ColumnMapping>  $columns
 */
function mappingWith(array $columns, ?int $productTypeId, ?Grouping $grouping = null): SheetMapping
{
    return new SheetMapping(productTypeId: $productTypeId, grouping: $grouping ?? new Grouping, columns: $columns);
}

test('a valid mapping passes', function () {
    $mapping = mappingWith([
        new ColumnMapping(0, 'Cod', 'variant.sku'),
        new ColumnMapping(1, 'Denumire', 'product.name', 'ro'),
        new ColumnMapping(2, 'Pret', 'variant.price', format: ColumnFormat::number(',', '.')),
        new ColumnMapping(3, 'Observatii', 'ignore'),
        new ColumnMapping(4, 'Altceva'),
    ], $this->productType->id);

    expect(app(MappingValidator::class)->errors($mapping))->toBe([]);
});

test('a weight column without a unit is refused naming the sheet', function () {
    $mapping = mappingWith([
        new ColumnMapping(0, 'Cod', 'variant.sku'),
        new ColumnMapping(1, 'Weight', 'variant.weight', format: ColumnFormat::number('.')),
    ], $this->productType->id);

    expect(fn () => app(MappingValidator::class)->validate($mapping, 'Produse'))
        ->toThrow(InvalidMapping::class, 'Choose the weight unit of the sheet "Produse".');

    $mapping->weightUnit = 'oz';

    expect(app(MappingValidator::class)->errors($mapping, 'Produse'))->toBe(['Choose the weight unit of the sheet "Produse".']);

    $mapping->weightUnit = 'g';

    expect(app(MappingValidator::class)->errors($mapping, 'Produse'))->toBe([]);
});

test('a sheet without a weight column needs no weight unit', function () {
    $mapping = mappingWith([new ColumnMapping(0, 'Cod', 'variant.sku')], $this->productType->id);

    expect(app(MappingValidator::class)->errors($mapping, 'Produse'))->toBe([]);
});

test('price mapped twice is rejected naming variant price', function () {
    $mapping = mappingWith([
        new ColumnMapping(0, 'Pret', 'variant.price', format: ColumnFormat::number(',')),
        new ColumnMapping(1, 'Pret redus', 'variant.price', format: ColumnFormat::number(',')),
    ], $this->productType->id);

    expect(fn () => app(MappingValidator::class)->validate($mapping))
        ->toThrow(InvalidMapping::class, 'Variant price (RON, excl. tax) is mapped twice.');
});

test('names in ro and hu are accepted but twice in one language is rejected', function () {
    $twoLanguages = mappingWith([
        new ColumnMapping(0, 'Denumire', 'product.name', 'ro'),
        new ColumnMapping(1, 'Megnevezés', 'product.name', 'hu'),
    ], $this->productType->id);

    $sameLanguage = mappingWith([
        new ColumnMapping(0, 'Denumire', 'product.name', 'ro'),
        new ColumnMapping(1, 'Nume', 'product.name', 'ro'),
    ], $this->productType->id);

    expect(app(MappingValidator::class)->errors($twoLanguages))->toBe([])
        ->and(app(MappingValidator::class)->errors($sameLanguage))->toBe(['Product name (ro) is mapped twice.']);
});

test('a new option without a name is rejected', function () {
    $mapping = mappingWith([
        new ColumnMapping(0, 'Marime', 'variant.option'),
    ], $this->productType->id);

    expect(app(MappingValidator::class)->errors($mapping))
        ->toBe(['Choose an existing option or name a new one for column "Marime".']);
});

test('an option is mapped at most once', function () {
    $size = ProductOption::factory()->create(['handle' => 'marime']);

    $mapping = mappingWith([
        new ColumnMapping(0, 'Marime', 'variant.option', optionId: $size->id),
        new ColumnMapping(1, 'Size', 'variant.option', optionId: $size->id),
        new ColumnMapping(2, 'Culoare', 'variant.option', optionName: 'Culoare'),
    ], $this->productType->id);

    expect(app(MappingValidator::class)->errors($mapping))->toHaveCount(1);
});

test('a deleted option is rejected', function () {
    $mapping = mappingWith([
        new ColumnMapping(0, 'Marime', 'variant.option', optionId: 999),
    ], $this->productType->id);

    expect(app(MappingValidator::class)->errors($mapping))->toBe(['The option of column "Marime" no longer exists.']);
});

test('a missing product type is rejected', function () {
    expect(app(MappingValidator::class)->errors(mappingWith([new ColumnMapping(0, 'Cod', 'variant.sku')], null)))
        ->toContain('Choose a product type.');
});

test('an unknown target or language is rejected', function () {
    $mapping = mappingWith([
        new ColumnMapping(0, 'Material', 'attribute.material', 'ro'),
        new ColumnMapping(1, 'Denumire', 'product.name', 'de'),
        new ColumnMapping(2, 'Descriere', 'product.description'),
    ], $this->productType->id);

    expect(app(MappingValidator::class)->errors($mapping))->toBe([
        'Column "Material" maps to a field that does not exist.',
        'Column "Denumire" uses a language that the store does not have.',
        'Choose a language for column "Descriere".',
    ]);
});

test('grouping by column needs a group key column', function () {
    $without = mappingWith([new ColumnMapping(0, 'Cod', 'variant.sku')], $this->productType->id, new Grouping(GroupingStrategy::ByColumn, 1));
    $with = mappingWith([
        new ColumnMapping(0, 'Cod', 'variant.sku'),
        new ColumnMapping(1, 'Cod parinte', 'group_key'),
    ], $this->productType->id, new Grouping(GroupingStrategy::ByColumn, 1));

    expect(app(MappingValidator::class)->errors($without))->toBe(['Grouping by column needs a column mapped to Group key.'])
        ->and(app(MappingValidator::class)->errors($with))->toBe([]);
});

test('import options default to sku, update, draft, and the first collection group, and hold no separators', function () {
    $group = CollectionGroup::factory()->create();
    CollectionGroup::factory()->create();

    $options = ImportOptions::defaults();

    expect($options->matchKey)->toBe(MatchKey::Sku)
        ->and($options->existingVariants)->toBe(ExistingVariants::Update)
        ->and($options->newProductStatus)->toBe(NewProductStatus::Draft)
        ->and($options->collectionGroupId)->toBe($group->id)
        ->and($options->toArray())->not->toHaveKeys(['path_separator', 'list_separator'])
        ->and(ImportOptions::restore($options->toArray())->toArray())->toBe($options->toArray());
});

test('a sheet mapping round-trips through an array', function () {
    $mapping = mappingWith([
        new ColumnMapping(0, 'Cod parinte', 'group_key', confidence: 0.74, flagged: true),
        new ColumnMapping(1, 'Marime', 'variant.option', optionName: 'Marime'),
    ], $this->productType->id, new Grouping(GroupingStrategy::ByColumn, 0, 0.74));

    $array = $mapping->toArray();

    expect($array['product_type_id'])->toBe($this->productType->id)
        ->and($array['columns'][1]['option_name'])->toBe('Marime')
        ->and(SheetMapping::from($array)->toArray())->toBe($array)
        ->and(SheetMapping::from($array)->column(1)->fieldPath())->toBe('variant.option.new:marime');
});

test('a price column without a number format is refused, naming the sheet and the column', function () {
    $mapping = mappingWith([
        new ColumnMapping(0, 'Cod', 'variant.sku'),
        new ColumnMapping(1, 'Regular price', 'variant.price'),
    ], $this->productType->id);

    expect(fn () => app(MappingValidator::class)->validate($mapping, 'wc-product-export'))
        ->toThrow(InvalidMapping::class, 'Choose the number format of the column "Regular price" on the sheet "wc-product-export".');

    $mapping->column(1)->format = ColumnFormat::number(',', ',');

    expect(app(MappingValidator::class)->errors($mapping, 'wc-product-export'))
        ->toBe(['Choose the number format of the column "Regular price" on the sheet "wc-product-export".']);

    $mapping->column(1)->format = ColumnFormat::number(',');

    expect(app(MappingValidator::class)->errors($mapping, 'wc-product-export'))->toBe([]);
});

test('a column of text needs no format', function () {
    $mapping = mappingWith([
        new ColumnMapping(0, 'Cod', 'variant.sku'),
        new ColumnMapping(1, 'Denumire', 'product.name', 'ro'),
    ], $this->productType->id);

    expect(app(MappingValidator::class)->errors($mapping, 'Produse'))->toBe([]);
});

test('option name pair 1 mapped twice is refused', function () {
    $mapping = mappingWith([
        new ColumnMapping(0, 'Attribute 1 name', 'variant.option_name', pair: 1, format: new ColumnFormat(listSeparator: ',')),
        new ColumnMapping(1, 'Attribute 1 value(s)', 'variant.option_value', pair: 1, format: new ColumnFormat(listSeparator: ',')),
        new ColumnMapping(2, 'Tulajdonság neve 1', 'variant.option_name', pair: 1, format: new ColumnFormat(listSeparator: ',')),
    ], $this->productType->id);

    expect(app(MappingValidator::class)->errors($mapping))->toBe(['Option name (pair) of pair 1 is mapped twice.']);
});

test('a pair 1 name without its value is refused', function () {
    $mapping = mappingWith([
        new ColumnMapping(0, 'Attribute 1 name', 'variant.option_name', pair: 1, format: new ColumnFormat(listSeparator: ',')),
        new ColumnMapping(1, 'Attribute 2 name', 'variant.option_name', pair: 2, format: new ColumnFormat(listSeparator: ',')),
        new ColumnMapping(2, 'Attribute 2 value(s)', 'variant.option_value', pair: 2, format: new ColumnFormat(listSeparator: ',')),
    ], $this->productType->id);

    expect(app(MappingValidator::class)->errors($mapping))->toBe(['Pair 1 has an option name column but no option value column.']);
});

test('an option name column without a pair number is refused', function () {
    $mapping = mappingWith([
        new ColumnMapping(0, 'Attribute 1 name', 'variant.option_name', format: new ColumnFormat(listSeparator: ',')),
    ], $this->productType->id);

    expect(app(MappingValidator::class)->errors($mapping))->toBe(['Choose the pair number of column "Attribute 1 name".']);
});

test('a dimension without a length unit is refused naming the sheet', function () {
    $mapping = mappingWith([
        new ColumnMapping(0, 'SKU', 'variant.sku'),
        new ColumnMapping(1, 'Length (cm)', 'variant.length', format: ColumnFormat::number('.')),
    ], $this->productType->id);

    expect(app(MappingValidator::class)->errors($mapping, 'wc-product-export'))->toBe(['Choose the length unit of the sheet "wc-product-export".']);

    $mapping->lengthUnit = 'cm';

    expect(app(MappingValidator::class)->errors($mapping, 'wc-product-export'))->toBe([]);
});

test('grouping by parent without a parent column is refused', function () {
    $without = mappingWith([new ColumnMapping(0, 'SKU', 'variant.sku')], $this->productType->id, new Grouping(GroupingStrategy::ByParent));
    $with = mappingWith([
        new ColumnMapping(0, 'SKU', 'variant.sku'),
        new ColumnMapping(1, 'Parent', 'source.parent'),
    ], $this->productType->id, new Grouping(GroupingStrategy::ByParent));

    expect(app(MappingValidator::class)->errors($without))->toBe(['Grouping by parent needs a column mapped to Parent reference.'])
        ->and(app(MappingValidator::class)->errors($with))->toBe([]);
});

test('the row type is accepted on any sheet', function () {
    $mapping = mappingWith([
        new ColumnMapping(0, 'SKU', 'variant.sku'),
        new ColumnMapping(1, 'Type', 'source.type', format: new ColumnFormat(values: ['simple' => 'single'], listSeparator: 'none')),
    ], $this->productType->id);

    expect(app(MappingValidator::class)->errors($mapping))->toBe([]);
});

test('item parts with two values and an image suffix with a slash are refused', function () {
    $mapping = mappingWith([
        new ColumnMapping(0, 'SKU', 'variant.sku'),
        new ColumnMapping(1, 'Features', 'product.properties', format: new ColumnFormat(itemParts: ['value', 'value'], listSeparator: ',', itemSeparator: ':')),
        new ColumnMapping(2, 'Images', 'product.images', format: new ColumnFormat(imageSuffix: '../thumbs', listSeparator: ',')),
    ], $this->productType->id);

    expect(app(MappingValidator::class)->errors($mapping))->toBe([
        'The item parts of the column "Features" may name one name and one value.',
        'The image size suffix of the column "Images" may hold only letters, digits, "-", and "_", and at most 50 characters.',
    ]);
});

test('a list column without a list separator is refused naming the sheet and the column, and none is accepted', function () {
    $mapping = mappingWith([
        new ColumnMapping(0, 'SKU', 'variant.sku'),
        new ColumnMapping(1, 'Tags', 'product.tags'),
    ], $this->productType->id);

    expect(app(MappingValidator::class)->errors($mapping, 'Produse'))
        ->toBe(['Choose the list separator of the column "Tags" on the sheet "Produse".']);

    $mapping->column(1)->format = new ColumnFormat(listSeparator: 'and');

    expect(app(MappingValidator::class)->errors($mapping, 'Produse'))
        ->toBe(['Choose the list separator of the column "Tags" on the sheet "Produse".']);

    $mapping->column(1)->format = new ColumnFormat(listSeparator: 'none');

    expect(app(MappingValidator::class)->errors($mapping, 'Produse'))->toBe([]);
});

test('a category column needs a path separator and a packed-items column an item separator', function () {
    $mapping = mappingWith([
        new ColumnMapping(0, 'SKU', 'variant.sku'),
        new ColumnMapping(1, 'Categorii', 'product.category', format: new ColumnFormat(listSeparator: ',')),
        new ColumnMapping(2, 'Options', 'variant.options', format: new ColumnFormat(listSeparator: ',')),
        new ColumnMapping(3, 'Attribute (Name:Type:Position)', 'variant.option_name', pair: 1, format: new ColumnFormat(itemParts: ['name', 'skip', 'skip'], listSeparator: ',')),
        new ColumnMapping(4, 'Value', 'variant.option_value', pair: 1, format: new ColumnFormat(listSeparator: ',')),
    ], $this->productType->id);

    expect(app(MappingValidator::class)->errors($mapping, 'Produse'))->toBe([
        'Choose the category level separator of the column "Categorii" on the sheet "Produse".',
        'Choose the item part separator of the column "Options" on the sheet "Produse".',
        'Choose the item part separator of the column "Attribute (Name:Type:Position)" on the sheet "Produse".',
    ]);

    $mapping->column(1)->format->pathSeparator = 'none';
    $mapping->column(2)->format->itemSeparator = '=';
    $mapping->column(3)->format->itemSeparator = ':';

    expect(app(MappingValidator::class)->errors($mapping, 'Produse'))->toBe([]);
});
