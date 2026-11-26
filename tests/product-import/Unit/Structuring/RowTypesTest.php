<?php

use Lunar\ProductImport\Data\ColumnFormat;
use Lunar\ProductImport\Data\ColumnMapping;
use Lunar\ProductImport\Data\NormalizedRow;
use Lunar\ProductImport\Data\SheetMapping;
use Lunar\ProductImport\Enums\RowRole;
use Lunar\ProductImport\Structuring\RowTypes;
use Lunar\ProductImport\Structuring\SkippedRow;

/**
 * A mapping whose type column gives the usual row types their meanings and
 * splits its cells on the given list separator.
 */
function rowTypesMapping(?ColumnFormat $format = null, string $listSeparator = ','): SheetMapping
{
    $format ??= ColumnFormat::meanings([
        'simple' => 'single',
        'variable' => 'parent',
        'variation' => 'variant',
        'grouped' => 'skip',
        'external' => 'skip',
        'virtual' => 'not_shippable',
        'downloadable' => 'not_shippable',
    ]);
    $format->listSeparator = $listSeparator;

    return new SheetMapping(columns: [
        new ColumnMapping(0, 'SKU', 'variant.sku'),
        new ColumnMapping(1, 'Type', 'source.type', format: $format),
    ]);
}

function rowTypes(?string $type, ?SheetMapping $mapping = null, string $listSeparator = ','): RowTypes
{
    return RowTypes::read($mapping ?? rowTypesMapping(listSeparator: $listSeparator), new NormalizedRow(['variant.sku' => 'TB-1', 'source.type' => $type]));
}

test('the first type item with a role decides the role', function (string $type, RowRole $role) {
    expect(rowTypes($type)->role)->toBe($role);
})->with([
    'parent' => ['variable', RowRole::Product],
    'variant' => ['variation', RowRole::Variant],
    'single' => ['simple', RowRole::ProductAndVariant],
    'in any case' => ['Variable', RowRole::Product],
    'after an item without a role' => ['virtual, simple', RowRole::ProductAndVariant],
    'first of two roles' => ['variation, simple', RowRole::Variant],
]);

test('a type that means not imported skips the row and names the type', function () {
    $types = rowTypes('grouped');

    expect($types->role)->toEqual(new SkippedRow('grouped'))
        ->and(rowTypes('External')->role)->toEqual(new SkippedRow('External'));
});

test('a not shippable item marks the variant of a single product', function () {
    $types = rowTypes('simple, virtual');

    expect($types->role)->toBe(RowRole::ProductAndVariant)
        ->and($types->notShippable)->toBeTrue()
        ->and($types->isSingle())->toBeTrue()
        ->and(rowTypes('simple')->notShippable)->toBeFalse()
        ->and(rowTypes('variation, downloadable')->notShippable)->toBeTrue();
});

test('a type without a meaning, an empty type, and a sheet without a type column give no role', function () {
    $withoutColumn = new SheetMapping(columns: [new ColumnMapping(0, 'SKU', 'variant.sku')]);

    expect(rowTypes('bundle')->role)->toBeNull()
        ->and(rowTypes('bundle')->notShippable)->toBeFalse()
        ->and(rowTypes(null)->role)->toBeNull()
        ->and(rowTypes('simple', $withoutColumn)->role)->toBeNull()
        ->and(rowTypes('simple', rowTypesMapping(new ColumnFormat))->role)->toBeNull();
});

test('the type cell is split on its column list separator', function () {
    expect(rowTypes('virtual|simple', listSeparator: '|')->role)->toBe(RowRole::ProductAndVariant)
        ->and(rowTypes('virtual, simple', listSeparator: 'none')->role)->toBeNull();
});

test('a meaning that is not a row type is not used', function () {
    $mapping = rowTypesMapping(ColumnFormat::meanings(['simple' => 'published', 'variable' => 'parent']));

    expect(rowTypes('simple', $mapping)->role)->toBeNull()
        ->and(rowTypes('variable', $mapping)->role)->toBe(RowRole::Product);
});
