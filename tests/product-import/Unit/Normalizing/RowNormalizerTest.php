<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\ProductImport\Data\ColumnFormat;
use Lunar\ProductImport\Data\ColumnMapping;
use Lunar\ProductImport\Data\NormalizedRow;
use Lunar\ProductImport\Data\SheetMapping;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Normalizing\RowNormalizer;
use Lunar\Tests\ProductImport\Support\ImportBuilder;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function normalizerMapping(?string $weightUnit = 'kg'): SheetMapping
{
    return new SheetMapping(productTypeId: 1, weightUnit: $weightUnit, columns: [
        new ColumnMapping(0, 'Cod', 'variant.sku'),
        new ColumnMapping(1, 'Denumire', 'product.name', 'ro'),
        new ColumnMapping(2, 'Pret', 'variant.price', format: ColumnFormat::number(',', '.')),
        new ColumnMapping(3, 'Stoc', 'variant.stock', format: ColumnFormat::number(',', '.')),
        new ColumnMapping(4, 'Greutate', 'variant.weight', format: ColumnFormat::number(',', '.')),
        new ColumnMapping(5, 'Categorie', 'product.category', format: new ColumnFormat(listSeparator: ',', pathSeparator: '>')),
        new ColumnMapping(6, 'Marime', 'variant.option', optionName: 'Marime'),
        new ColumnMapping(7, 'Observatii', 'ignore'),
        new ColumnMapping(8, 'Neatins'),
        new ColumnMapping(9, 'Brand', 'product.brand'),
        new ColumnMapping(10, 'EAN', 'variant.ean'),
    ]);
}

test('cells are normalized by field path', function () {
    $row = app(RowNormalizer::class)->normalize(
        [' TB-1-S ', 'Tricou basic', '1.249,90 lei', '12', '250', 'Femei > Tricouri >  ', 'M', 'nota', 'x', ' NIKE  Inc ', '594 1234 567890'],
        normalizerMapping('g'),
        100,
    );

    expect($row->values)->toBe([
        'variant.sku' => 'TB-1-S',
        'product.name.ro' => 'Tricou basic',
        'variant.price' => 124990,
        'variant.stock' => 12,
        'variant.weight' => 250.0,
        'product.category' => [['Femei', 'Tricouri']],
        'variant.option.new:marime' => 'M',
        'product.brand' => 'NIKE Inc',
        'variant.ean' => '5941234567890',
    ])->and($row->invalid)->toBe([]);
});

test('no-break and zero-width spaces from excel are trimmed', function () {
    $row = app(RowNormalizer::class)->normalize(
        ["TB-001-S\u{00A0}", "\u{200B}Tricou basic"],
        normalizerMapping(),
        100,
    );

    expect($row->values['variant.sku'])->toBe('TB-001-S')
        ->and($row->values['product.name.ro'])->toBe('Tricou basic');
});

test('a weight stays in the unit of its sheet', function (string $unit) {
    $row = app(RowNormalizer::class)->normalize(['TB-1', 'Tricou', '1', '1', '250'], normalizerMapping($unit), 100);

    expect($row->values['variant.weight'])->toBe(250.0);
})->with(['g', 'kg', 'lbs']);

test('status cells are read as published or draft regardless of case', function (string $cell, ?string $status) {
    $mapping = new SheetMapping(columns: [new ColumnMapping(0, 'Cod', 'variant.sku'), new ColumnMapping(1, 'Published', 'product.status')]);

    $row = app(RowNormalizer::class)->normalize(['TB-1', $cell], $mapping, 100);

    expect($row->values['product.status'])->toBe($status)
        ->and($row->invalid)->toBe($status === null ? ['product.status' => $cell] : []);
})->with([
    ['1', 'published'],
    ['Igen', 'published'],
    ['DA', 'published'],
    ['Published', 'published'],
    ['aktív', 'published'],
    ['-1', 'draft'],
    ['0', 'draft'],
    ['nu', 'draft'],
    ['Private', 'draft'],
    ['maybe', null],
]);

test('a category cell is read as a list of category paths with its column separators', function (string $cell, string $listSeparator, string $pathSeparator, array $paths) {
    $mapping = new SheetMapping(columns: [
        new ColumnMapping(0, 'Cod', 'variant.sku'),
        new ColumnMapping(1, 'Categories', 'product.category', format: new ColumnFormat(listSeparator: $listSeparator, pathSeparator: $pathSeparator)),
    ]);

    $row = app(RowNormalizer::class)->normalize(['TB-1', $cell], $mapping, 100);

    expect($row->values['product.category'])->toBe($paths);
})->with([
    'list of paths' => ['Femei > Rochii, Femei > Noutăți', ',', '>', [['Femei', 'Rochii'], ['Femei', 'Noutăți']]],
    'escaped separator inside a name' => ['Rochii\\, fuste', ',', '>', [['Rochii, fuste']]],
    'one category per cell' => ['Femei > Rochii, fuste', 'none', '>', [['Femei', 'Rochii, fuste']]],
    'repeated path' => ['Femei > Rochii, femei >  rochii', ',', '>', [['Femei', 'Rochii']]],
    'semicolon list' => ['Femei;Barbati > Geci', ';', '>', [['Femei'], ['Barbati', 'Geci']]],
    'pipe list of slash paths' => ['Femei/Rochii|Femei/Noutăți', '|', '/', [['Femei', 'Rochii'], ['Femei', 'Noutăți']]],
    'categories without levels' => ['Femei > Rochii, Accesorii', ',', 'none', [['Femei > Rochii'], ['Accesorii']]],
]);

test('a list column without a separator reads each cell as one item', function () {
    $mapping = new SheetMapping(productTypeId: 1, columns: [
        new ColumnMapping(0, 'Tags', 'product.tags'),
        new ColumnMapping(1, 'Categories', 'product.category'),
    ]);

    $row = app(RowNormalizer::class)->normalize(['vara, bumbac', 'Femei > Rochii'], $mapping, 100);

    expect($row->values['product.tags'])->toBe(['vara, bumbac'])
        ->and($row->values['product.category'])->toBe([['Femei > Rochii']]);
});

test('ignored and unmapped columns never appear', function () {
    $row = app(RowNormalizer::class)->normalize(['A', 'B', '1', '1', '1', 'C', 'S', 'nota', 'x', 'Nike', '1'], normalizerMapping(), 100);

    expect(array_keys($row->values))->not->toContain('ignore')
        ->and(json_encode($row->toArray()))->not->toContain('nota')
        ->not->toContain('"x"');
});

test('empty mapped cells are null and unreadable numbers keep their raw text', function () {
    $row = app(RowNormalizer::class)->normalize(['TB-1', 'Tricou', '', 'la comanda', '1,5'], normalizerMapping(), 100);

    expect($row->values['variant.price'])->toBeNull()
        ->and($row->has('variant.price'))->toBeTrue()
        ->and($row->values['variant.stock'])->toBeNull()
        ->and($row->invalid)->toBe(['variant.stock' => 'la comanda'])
        ->and($row->values['variant.weight'])->toBe(1.5);
});

test('a stock with a fraction is unreadable', function () {
    $row = app(RowNormalizer::class)->normalize(['TB-1', 'Tricou', '1', '2,5'], normalizerMapping(), 100);

    expect($row->invalid)->toBe(['variant.stock' => '2,5']);
});

test('an override wins over the normalized value and the raw value stays unchanged', function () {
    $import = ImportBuilder::import(ImportStatus::PreviewReady);
    $sheet = ImportBuilder::sheet($import, 'Produse', [['Cod', 'Pret', 'Stoc'], ['TB-1', '', 'la comanda']]);
    $row = $sheet->rows()->where('row_number', 2)->first();

    $normalized = app(RowNormalizer::class)->normalize($row->raw, new SheetMapping(columns: [
        new ColumnMapping(0, 'Cod', 'variant.sku'),
        new ColumnMapping(1, 'Pret', 'variant.price'),
        new ColumnMapping(2, 'Stoc', 'variant.stock'),
    ]), 100);

    $row->update(['normalized' => $normalized->toArray(), 'overrides' => ['variant.price' => 4990, 'variant.stock' => 3]]);
    $row = ProductImportRow::find($row->id);

    expect($row->effectiveRow()->values)->toBe(['variant.sku' => 'TB-1', 'variant.price' => 4990, 'variant.stock' => 3])
        ->and($row->effectiveRow()->invalid)->toBe([])
        ->and(NormalizedRow::fromArray($row->normalized)->invalid)->toBe(['variant.stock' => 'la comanda'])
        ->and($row->raw)->toBe([0 => 'TB-1', 2 => 'la comanda']);
});

test('each column reads its numbers in its own format', function () {
    $mapping = new SheetMapping(productTypeId: 1, weightUnit: 'kg', columns: [
        new ColumnMapping(0, 'SKU', 'variant.sku'),
        new ColumnMapping(1, 'Regular price', 'variant.price', format: ColumnFormat::number(',')),
        new ColumnMapping(2, 'Weight (kg)', 'variant.weight', format: ColumnFormat::number('.')),
    ]);

    $row = app(RowNormalizer::class)->normalize(['TB-1', '1256,59', '1.250'], $mapping, 100);

    expect($row->values)->toBe(['variant.sku' => 'TB-1', 'variant.price' => 125659, 'variant.weight' => 1.25])
        ->and($row->invalid)->toBe([]);
});

test('a value in another format keeps its raw text instead of being guessed', function () {
    $mapping = new SheetMapping(productTypeId: 1, weightUnit: 'kg', columns: [
        new ColumnMapping(0, 'SKU', 'variant.sku'),
        new ColumnMapping(1, 'Greutate', 'variant.weight', format: ColumnFormat::number('.')),
    ]);

    $row = app(RowNormalizer::class)->normalize(['TB-1', '1,25'], $mapping, 100);

    expect($row->values['variant.weight'])->toBeNull()
        ->and($row->invalid)->toBe(['variant.weight' => '1,25']);
});

test('a typed cell is read in its fixed form whatever the column format is', function () {
    $mapping = new SheetMapping(productTypeId: 1, columns: [
        new ColumnMapping(0, 'SKU', 'variant.sku'),
        new ColumnMapping(1, 'Pret', 'variant.price', format: ColumnFormat::number(',')),
    ]);

    $typed = app(RowNormalizer::class)->normalize(['TB-1', '1256.59'], $mapping, 100, [1]);
    $text = app(RowNormalizer::class)->normalize(['TB-2', '49,90'], $mapping, 100);

    expect($typed->values['variant.price'])->toBe(125659)
        ->and($text->values['variant.price'])->toBe(4990);
});

test('a number column without a format reads nothing', function () {
    $mapping = new SheetMapping(productTypeId: 1, columns: [
        new ColumnMapping(0, 'SKU', 'variant.sku'),
        new ColumnMapping(1, 'Pret', 'variant.price'),
    ]);

    $row = app(RowNormalizer::class)->normalize(['TB-1', '49,90'], $mapping, 100);

    expect($row->invalid)->toBe(['variant.price' => '49,90']);
});

test('list cells are split on the list separator', function () {
    $mapping = new SheetMapping(productTypeId: 1, columns: [
        new ColumnMapping(0, 'SKU', 'variant.sku'),
        new ColumnMapping(1, 'Tags', 'product.tags', format: new ColumnFormat(listSeparator: ',')),
        new ColumnMapping(2, 'Images', 'product.images', format: new ColumnFormat(listSeparator: ',')),
        new ColumnMapping(3, 'Upsells', 'product.upsells', format: new ColumnFormat(listSeparator: ',')),
        new ColumnMapping(4, 'Attribute 1 name', 'variant.option_name', pair: 1, format: new ColumnFormat(listSeparator: ',')),
        new ColumnMapping(5, 'Attribute 1 value(s)', 'variant.option_value', pair: 1, format: new ColumnFormat(listSeparator: ',')),
        new ColumnMapping(6, 'Options', 'variant.options', format: new ColumnFormat(listSeparator: ',', itemSeparator: ':')),
        new ColumnMapping(7, 'Features', 'product.properties', format: new ColumnFormat(listSeparator: ',', itemSeparator: ':')),
    ]);

    $row = app(RowNormalizer::class)->normalize([
        'TB-1', 'vara, bumbac, Vara', 'https://shop.test/a.jpg, https://shop.test/b.jpg', 'id:57, TB-001-S',
        'Size, Color', 'S, Red', 'Color:Red, Size:M', 'Compoziție:Bumbac 100%',
    ], $mapping, 100);

    expect($row->values['product.tags'])->toBe(['vara', 'bumbac'])
        ->and($row->values['product.images'])->toBe(['https://shop.test/a.jpg', 'https://shop.test/b.jpg'])
        ->and($row->values['product.upsells'])->toBe(['id:57', 'TB-001-S'])
        ->and($row->values['variant.option_name.1'])->toBe(['Size', 'Color'])
        ->and($row->values['variant.option_value.1'])->toBe(['S', 'Red'])
        ->and($row->values['variant.options'])->toBe([['name' => 'Color', 'value' => 'Red'], ['name' => 'Size', 'value' => 'M']])
        ->and($row->values['product.properties'])->toBe([['name' => 'Compoziție', 'value' => 'Bumbac 100%']]);
});

test('a list separator after a backslash belongs to the item', function () {
    $mapping = new SheetMapping(productTypeId: 1, columns: [new ColumnMapping(0, 'Tags', 'product.tags', format: new ColumnFormat(listSeparator: ','))]);

    $row = app(RowNormalizer::class)->normalize(['Rochii\\, fuste'], $mapping, 100);

    expect($row->values['product.tags'])->toBe(['Rochii, fuste']);
});

test('another list separator splits the images on it', function () {
    $mapping = new SheetMapping(productTypeId: 1, columns: [new ColumnMapping(0, 'Image URLs', 'product.images', format: new ColumnFormat(listSeparator: '|'))]);

    $row = app(RowNormalizer::class)->normalize(['https://shop.test/a.jpg|https://shop.test/b,1.jpg'], $mapping, 100);

    expect($row->values['product.images'])->toBe(['https://shop.test/a.jpg', 'https://shop.test/b,1.jpg']);
});

test('packed options are split on the column list and item separators', function () {
    $mapping = new SheetMapping(productTypeId: 1, columns: [
        new ColumnMapping(0, 'Options', 'variant.options', format: new ColumnFormat(listSeparator: ';', itemSeparator: '=')),
    ]);

    $row = app(RowNormalizer::class)->normalize(['Size=M; Color=Red'], $mapping, 100);

    expect($row->values['variant.options'])->toBe([['name' => 'Size', 'value' => 'M'], ['name' => 'Color', 'value' => 'Red']]);
});

test('booleans, stock statuses, dates, and the other numbers are read by their target', function () {
    $mapping = new SheetMapping(productTypeId: 1, weightUnit: 'kg', lengthUnit: 'cm', columns: [
        new ColumnMapping(0, 'SKU', 'variant.sku'),
        new ColumnMapping(1, 'In stock?', 'variant.stock_status'),
        new ColumnMapping(2, 'Backorders', 'variant.backorders'),
        new ColumnMapping(3, 'Shippable', 'variant.shippable'),
        new ColumnMapping(4, 'Sale price', 'variant.sale_price', format: ColumnFormat::number(',')),
        new ColumnMapping(5, 'Sale ends', 'variant.sale_ends', format: ColumnFormat::date('Y-m-d G:i:s')),
        new ColumnMapping(6, 'Length (cm)', 'variant.length', format: ColumnFormat::number('.')),
        new ColumnMapping(7, 'Min qty', 'variant.min_quantity', format: ColumnFormat::number('.')),
        new ColumnMapping(8, 'Impact', 'variant.price_impact', format: ColumnFormat::number('.')),
        new ColumnMapping(9, 'Discount', 'variant.discount_percent', format: ColumnFormat::number('.')),
        new ColumnMapping(10, 'GTIN', 'variant.gtin'),
    ]);

    $row = app(RowNormalizer::class)->normalize(
        ['TB-1', 'backorder', 'Igen', 'nu', '149,00', '2026-12-24 23:59:59', '30.5', '2', '-15.50', '25', '594 1234 567890'],
        $mapping,
        100,
    );

    expect($row->values)->toMatchArray([
        'variant.stock_status' => 'on_backorder',
        'variant.backorders' => true,
        'variant.shippable' => false,
        'variant.sale_price' => 14900,
        'variant.sale_ends' => '2026-12-24 23:59:59',
        'variant.length' => 30.5,
        'variant.min_quantity' => 2,
        'variant.price_impact' => -1550,
        'variant.discount_percent' => 25.0,
        'variant.gtin' => '5941234567890',
    ])->and($row->invalid)->toBe([]);

    $unknown = app(RowNormalizer::class)->normalize(['TB-1', 'maybe'], $mapping, 100);

    expect($unknown->invalid)->toBe(['variant.stock_status' => 'maybe']);
});

test('every cell is cleaned on a sheet without any column convention', function () {
    $mapping = new SheetMapping(productTypeId: 1, columns: [
        new ColumnMapping(0, 'Cod', 'variant.sku'),
        new ColumnMapping(1, 'Oferta', 'variant.option', optionName: 'Oferta'),
        new ColumnMapping(2, 'Stoc', 'variant.stock', format: ColumnFormat::number(',', '.')),
        new ColumnMapping(3, 'Nota', 'product.name', 'ro'),
        new ColumnMapping(4, 'Descriere', 'product.description', 'ro'),
        new ColumnMapping(5, 'Brand', 'product.brand'),
    ]);

    $row = app(RowNormalizer::class)->normalize(
        ['TB-1', "'-20%", "'-3", "'abc", 'Rochie lejeră.\nBumbac 100%.', 'Dolce &amp;amp; Gabbana'],
        $mapping,
        100,
    );

    expect($row->values)->toMatchArray([
        'variant.option.new:oferta' => '-20%',
        'variant.stock' => -3,
        'product.name.ro' => "'abc",
        'product.description.ro' => "Rochie lejeră.\nBumbac 100%.",
        'product.brand' => 'Dolce & Gabbana',
    ])->and($row->invalid)->toBe([]);

    expect(app(RowNormalizer::class)->normalizeField($mapping, 'variant.stock', "'-3", 100)->values)
        ->toBe(['variant.stock' => -3]);
});

test('a code is read with the column meaning before the built-in words', function () {
    $status = fn (?ColumnFormat $format): SheetMapping => new SheetMapping(productTypeId: 1, columns: [
        new ColumnMapping(0, 'SKU', 'variant.sku'),
        new ColumnMapping(1, 'Published', 'product.status', format: $format),
    ]);

    $withMeaning = app(RowNormalizer::class)->normalize(['TB-1', '2'], $status(ColumnFormat::meanings(['2' => 'draft', '1' => 'published'])), 100);
    $withoutMeaning = app(RowNormalizer::class)->normalize(['TB-1', '2'], $status(null), 100);
    $builtIn = app(RowNormalizer::class)->normalize(['TB-1', 'Igen'], $status(ColumnFormat::meanings(['2' => 'draft'])), 100);

    expect($withMeaning->values['product.status'])->toBe('draft')
        ->and($withMeaning->invalid)->toBe([])
        ->and($withoutMeaning->values['product.status'])->toBeNull()
        ->and($withoutMeaning->invalid)->toBe(['product.status' => '2'])
        ->and($builtIn->values['product.status'])->toBe('published');
});

test('backorder and shippable codes are read with their meanings', function () {
    $mapping = new SheetMapping(productTypeId: 1, columns: [
        new ColumnMapping(0, 'SKU', 'variant.sku'),
        new ColumnMapping(1, 'Backorders', 'variant.backorders', format: ColumnFormat::meanings(['notify' => 'yes', '2' => 'empty'])),
        new ColumnMapping(2, 'Virtual product', 'variant.shippable', format: ColumnFormat::meanings(['1' => 'no', '0' => 'yes'])),
        new ColumnMapping(3, 'In stock?', 'variant.stock_status', format: ColumnFormat::meanings(['backorder' => 'on_backorder', '1' => 'in_stock'])),
    ]);

    $notify = app(RowNormalizer::class)->normalize(['TB-1', 'Notify', '1', 'backorder'], $mapping, 100);
    $unset = app(RowNormalizer::class)->normalize(['TB-2', '2', '0', '1'], $mapping, 100);

    expect($notify->values)->toMatchArray(['variant.backorders' => true, 'variant.shippable' => false, 'variant.stock_status' => 'on_backorder'])
        ->and($unset->values)->toMatchArray(['variant.backorders' => null, 'variant.shippable' => true, 'variant.stock_status' => 'in_stock'])
        ->and($unset->invalid)->toBe([]);
});

test('a meaning that does not fit the target is not used', function () {
    $mapping = new SheetMapping(productTypeId: 1, columns: [
        new ColumnMapping(0, 'Backorders', 'variant.backorders', format: ColumnFormat::meanings(['2' => 'published'])),
    ]);

    expect(app(RowNormalizer::class)->normalize(['2'], $mapping, 100)->invalid)->toBe(['variant.backorders' => '2']);
});

test('item parts keep only the name or the value of each packed item', function () {
    $mapping = new SheetMapping(productTypeId: 1, columns: [
        new ColumnMapping(0, 'Option names', 'variant.option_name', format: new ColumnFormat(itemParts: ['name', 'skip', 'skip'], listSeparator: ',', itemSeparator: ':'), pair: 1),
        new ColumnMapping(1, 'Option values', 'variant.option_value', format: new ColumnFormat(itemParts: ['value', 'skip'], listSeparator: ',', itemSeparator: ':'), pair: 1),
        new ColumnMapping(2, 'Features', 'product.properties', format: new ColumnFormat(itemParts: ['name', 'value', 'skip'], listSeparator: ',', itemSeparator: ':')),
        new ColumnMapping(3, 'Options', 'variant.options', format: new ColumnFormat(listSeparator: ',', itemSeparator: ':')),
    ]);

    $row = app(RowNormalizer::class)->normalize(
        ['Size:select:0, Color:color:1', 'S:0, Alb:1', 'Compoziție:Bumbac 100%:1', 'Format:16:9'],
        $mapping,
        100,
    );

    expect($row->values)->toBe([
        'variant.option_name.1' => ['Size', 'Color'],
        'variant.option_value.1' => ['S', 'Alb'],
        'product.properties' => [['name' => 'Compoziție', 'value' => 'Bumbac 100%']],
        'variant.options' => [['name' => 'Format', 'value' => '16:9']],
    ]);
});

test('image urls lose the size suffix of their column', function () {
    $mapping = new SheetMapping(productTypeId: 1, columns: [
        new ColumnMapping(0, 'Images', 'product.images', format: new ColumnFormat(imageSuffix: '-small_default', listSeparator: ',')),
        new ColumnMapping(1, 'Variant image', 'variant.image', format: new ColumnFormat(imageSuffix: '-small_default', listSeparator: 'none')),
        new ColumnMapping(2, 'Other images', 'variant.sku'),
    ]);

    $row = app(RowNormalizer::class)->normalize(
        [
            'https://shop.example.com/img/p/1/2/12-small_default.jpg, https://shop.example.com/12-small_default/rochie.jpg',
            'https://shop.example.com/img/p/1/4/14-small_default.png?v=2',
            'TB-1',
        ],
        $mapping,
        100,
    );

    expect($row->values['product.images'])->toBe(['https://shop.example.com/img/p/1/2/12.jpg', 'https://shop.example.com/12/rochie.jpg'])
        ->and($row->values['variant.image'])->toBe(['https://shop.example.com/img/p/1/4/14.png?v=2']);
});

test('a url slug is made the way Lunar makes slugs, in the column language', function () {
    $mapping = new SheetMapping(productTypeId: 1, columns: [
        new ColumnMapping(0, 'SKU', 'variant.sku'),
        new ColumnMapping(1, 'URL key', 'product.slug', 'ro'),
    ]);

    $slug = app(RowNormalizer::class)->normalize(['TB-1', 'Rochie Vară Roșie'], $mapping, 100);
    $empty = app(RowNormalizer::class)->normalize(['TB-2', '!!!'], $mapping, 100);

    expect($slug->values['product.slug.ro'])->toBe('rochie-vara-rosie')
        ->and($empty->values['product.slug.ro'])->toBeNull()
        ->and($empty->invalid)->toBe(['product.slug.ro' => '!!!']);
});
