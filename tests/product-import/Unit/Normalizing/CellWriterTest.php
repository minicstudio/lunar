<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\ProductImport\Data\ColumnFormat;
use Lunar\ProductImport\Data\ColumnMapping;
use Lunar\ProductImport\Data\SheetMapping;
use Lunar\ProductImport\Normalizing\CellWriter;
use Lunar\ProductImport\Normalizing\RowNormalizer;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('a value written for its column reads back as the same value', function (ColumnMapping $column, mixed $value, string $text) {
    $mapping = new SheetMapping(productTypeId: 1, weightUnit: 'kg', lengthUnit: 'cm', columns: [$column]);

    $written = app(CellWriter::class)->write($column, $value, 100);
    $read = app(RowNormalizer::class)->normalizeField($mapping, (string) $column->fieldPath(), $written, 100);

    expect($written)->toBe($text)
        ->and($read->invalid)->toBe([])
        ->and($read->get((string) $column->fieldPath()))->toBe($value);
})->with([
    'price on a decimal comma column' => [new ColumnMapping(0, 'Pret', 'variant.price', format: ColumnFormat::number(',', '.')), 4990, '49,90'],
    'sale price in major units' => [new ColumnMapping(0, 'Pret promotional', 'variant.sale_price', format: ColumnFormat::number(',')), 14900, '149,00'],
    'negative price impact' => [new ColumnMapping(0, 'Impact', 'variant.price_impact', format: ColumnFormat::number('.')), -1550, '-15.50'],
    'discount amount' => [new ColumnMapping(0, 'Reducere', 'variant.discount_amount', format: ColumnFormat::number(',')), 2500, '25,00'],
    'weight with a dot' => [new ColumnMapping(0, 'Weight (kg)', 'variant.weight', format: ColumnFormat::number('.')), 1.25, '1.25'],
    'weight with a comma' => [new ColumnMapping(0, 'Greutate', 'variant.weight', format: ColumnFormat::number(',', '.')), 1250.5, '1250,5'],
    'stock' => [new ColumnMapping(0, 'Stoc', 'variant.stock', format: ColumnFormat::number(',', '.')), 12, '12'],
    'sale end' => [new ColumnMapping(0, 'Sale ends', 'variant.sale_ends', format: ColumnFormat::date('d.m.Y H:i')), '2026-12-24 23:59:00', '24.12.2026 23:59'],
    'tags' => [new ColumnMapping(0, 'Etichete', 'product.tags', format: new ColumnFormat(listSeparator: ',')), ['vara', 'bumbac'], 'vara, bumbac'],
    'tags with the separator in a name' => [new ColumnMapping(0, 'Etichete', 'product.tags', format: new ColumnFormat(listSeparator: ';')), ['vara; toamna', 'bumbac'], 'vara\; toamna; bumbac'],
    'images separated by a pipe' => [new ColumnMapping(0, 'Images', 'product.images', format: new ColumnFormat(listSeparator: '|')), ['https://cdn.example/1.jpg', 'https://cdn.example/2.jpg'], 'https://cdn.example/1.jpg| https://cdn.example/2.jpg'],
    'categories' => [new ColumnMapping(0, 'Categorii', 'product.category', format: new ColumnFormat(listSeparator: '|', pathSeparator: '/')), [['Femei', 'Rochii'], ['Femei', 'Noutăți']], 'Femei / Rochii| Femei / Noutăți'],
    'packed options' => [new ColumnMapping(0, 'Options', 'variant.options', format: new ColumnFormat(listSeparator: ';', itemSeparator: '=')), [['name' => 'Size', 'value' => 'M'], ['name' => 'Color', 'value' => 'Red']], 'Size=M; Color=Red'],
    'properties with parts' => [new ColumnMapping(0, 'Features', 'product.properties', format: new ColumnFormat(itemParts: ['name', 'value', 'skip'], listSeparator: ',', itemSeparator: ':')), [['name' => 'Compoziție', 'value' => 'Bumbac 100%']], 'Compoziție:Bumbac 100%'],
    'option values with parts' => [new ColumnMapping(0, 'Values', 'variant.option_value', format: new ColumnFormat(itemParts: ['skip', 'value'], listSeparator: ',', itemSeparator: ':'), pair: 1), ['S', 'Alb'], ':S, :Alb'],
    'backorders with the column code' => [new ColumnMapping(0, 'Out of stock action', 'variant.backorders', format: ColumnFormat::meanings(['0' => 'no', '1' => 'yes'])), true, '1'],
    'inverted shippable flag' => [new ColumnMapping(0, 'Virtual product', 'variant.shippable', format: ColumnFormat::meanings(['1' => 'no', '0' => 'yes'])), true, '0'],
    'backorders without codes' => [new ColumnMapping(0, 'Backorders', 'variant.backorders'), false, 'no'],
    'status with the column code' => [new ColumnMapping(0, 'Published', 'product.status', format: ColumnFormat::meanings(['1' => 'published', '2' => 'draft'])), 'draft', '2'],
    'stock status without codes' => [new ColumnMapping(0, 'In stock?', 'variant.stock_status'), 'on_backorder', 'on_backorder'],
    'url slug' => [new ColumnMapping(0, 'URL key', 'product.slug', 'ro'), 'rochie-vara', 'rochie-vara'],
]);
