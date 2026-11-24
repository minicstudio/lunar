<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Models\Language;
use Lunar\Models\ProductOption;
use Lunar\ProductImport\Analysis\ProposalValidator;
use Lunar\ProductImport\Data\ColumnFormat;
use Lunar\ProductImport\Enums\GroupingStrategy;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Exceptions\ProposalRejected;
use Lunar\ProductImport\Models\ProductImportSheet;
use Lunar\Tests\ProductImport\Support\ImportBuilder;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Language::factory()->create(['code' => 'ro', 'default' => true]);
    Language::factory()->create(['code' => 'hu', 'default' => false]);

    $import = ImportBuilder::import(ImportStatus::Analyzing);
    $this->sheet = ImportBuilder::sheet($import, 'Produse', [
        ['Denumire', 'Cod parinte', 'Cod', 'Cod furnizor', 'Observatii', 'Marime'],
        ['Tricou', 'TB-1', 'TB-1-S', 'F-1', 'nota', 'S'],
    ]);
});

/**
 * A valid answer, with the given column entries replacing the defaults.
 *
 * @param  array<int, array<string, mixed>>  $columns
 * @return array<string, mixed>
 */
function answerWith(array $columns = [], array $grouping = ['strategy' => 'by_column', 'column' => 1, 'confidence' => 0.74]): array
{
    $defaults = [
        0 => ['index' => 0, 'target' => 'product.name', 'confidence' => 0.96, 'locale' => 'ro', 'option_name' => null],
        1 => ['index' => 1, 'target' => 'group_key', 'confidence' => 0.74, 'locale' => null, 'option_name' => null],
        2 => ['index' => 2, 'target' => 'variant.sku', 'confidence' => 0.97, 'locale' => null, 'option_name' => null],
        4 => ['index' => 4, 'target' => 'ignore', 'confidence' => 0.40, 'locale' => null, 'option_name' => null],
    ];

    return [
        'columns' => array_values(array_replace($defaults, $columns)),
        'grouping' => $grouping,
        'units' => ['weight_unit' => null, 'length_unit' => null],
    ];
}

test('a valid answer becomes the prefilled mapping', function () {
    $mapping = app(ProposalValidator::class)->toMapping(answerWith(), $this->sheet, null);

    expect($mapping->column(0)->target)->toBe('product.name')
        ->and($mapping->column(0)->locale)->toBe('ro')
        ->and($mapping->column(0)->confidence)->toBe(0.96)
        ->and($mapping->column(0)->flagged)->toBeFalse()
        ->and($mapping->column(1)->target)->toBe('group_key')
        ->and($mapping->column(1)->flagged)->toBeTrue()
        ->and($mapping->column(4)->target)->toBeNull()
        ->and($mapping->column(4)->suggestedTarget)->toBe('ignore')
        ->and($mapping->column(3)->target)->toBeNull()
        ->and($mapping->grouping->strategy)->toBe(GroupingStrategy::ByColumn)
        ->and($mapping->grouping->column)->toBe(1)
        ->and($mapping->grouping->confidence)->toBe(0.74);
});

test('the weight unit is kept only when it is one of the store units', function (?string $answered, ?string $unit) {
    $answer = answerWith();
    $answer['units']['weight_unit'] = $answered;

    $mapping = app(ProposalValidator::class)->toMapping($answer, $this->sheet, null);

    expect($mapping->weightUnit)->toBe($unit);
})->with([
    'g' => ['g', 'g'],
    'kg' => ['kg', 'kg'],
    'lbs' => ['lbs', 'lbs'],
    'grams is not translated' => ['grams', null],
    'G is not translated' => ['G', null],
    'oz with the default measurements' => ['oz', null],
    'no unit' => [null, null],
]);

test('an option column is matched to an existing option by name', function () {
    $size = ProductOption::factory()->create(['name' => ['en' => 'Mărime'], 'handle' => 'marime']);

    $mapping = app(ProposalValidator::class)->toMapping(answerWith([
        5 => ['index' => 5, 'target' => 'variant.option', 'confidence' => 0.9, 'locale' => null, 'option_name' => 'marime'],
    ]), $this->sheet, null);

    expect($mapping->column(5)->optionId)->toBe($size->id)
        ->and($mapping->column(5)->optionName)->toBeNull();
});

test('an unknown column is rejected', function () {
    app(ProposalValidator::class)->toMapping(answerWith([
        9 => ['index' => 9, 'target' => 'variant.stock', 'confidence' => 0.9, 'locale' => null, 'option_name' => null],
    ]), $this->sheet, null);
})->throws(ProposalRejected::class, 'column 9 does not exist');

test('an unknown target is rejected', function () {
    app(ProposalValidator::class)->toMapping(answerWith([
        3 => ['index' => 3, 'target' => 'variant.supplier_code', 'confidence' => 0.9, 'locale' => null, 'option_name' => null],
    ]), $this->sheet, null);
})->throws(ProposalRejected::class, 'the field "variant.supplier_code" is not allowed');

test('an unknown language is rejected', function () {
    app(ProposalValidator::class)->toMapping(answerWith([
        0 => ['index' => 0, 'target' => 'product.name', 'confidence' => 0.96, 'locale' => 'de', 'option_name' => null],
    ]), $this->sheet, null);
})->throws(ProposalRejected::class, 'the language "de" is not a store language');

test('two sku columns are rejected', function () {
    try {
        app(ProposalValidator::class)->toMapping(answerWith([
            3 => ['index' => 3, 'target' => 'variant.sku', 'confidence' => 0.8, 'locale' => null, 'option_name' => null],
        ]), $this->sheet, null);

        $this->fail('The answer was accepted.');
    } catch (ProposalRejected $exception) {
        expect($exception->failureCode)->toBe('invalid_response')
            ->and($exception->reason)->toBe('two columns were marked as Variant SKU');
    }
});

test('a missing grouping column is rejected', function () {
    app(ProposalValidator::class)->toMapping(answerWith(grouping: ['strategy' => 'by_column', 'column' => 12, 'confidence' => 0.8]), $this->sheet, null);
})->throws(ProposalRejected::class, 'the grouping column does not exist');

test('an answer without a name and without a sku has no product data', function () {
    try {
        app(ProposalValidator::class)->toMapping([
            'columns' => [
                ['index' => 3, 'target' => 'variant.ean', 'confidence' => 0.9, 'locale' => null, 'option_name' => null],
            ],
            'grouping' => ['strategy' => 'per_row', 'column' => null, 'confidence' => 0.9],
        ], $this->sheet, null);

        $this->fail('The answer was accepted.');
    } catch (ProposalRejected $exception) {
        expect($exception->failureCode)->toBe('no_product_data');
    }
});

test('an answer without a column list is rejected', function () {
    app(ProposalValidator::class)->toMapping(['grouping' => []], $this->sheet, null);
})->throws(ProposalRejected::class, 'the answer has no column list');

/**
 * An export sheet from a shop with a decimal comma: prices with the comma,
 * weights with a dot.
 */
function decimalCommaSheet(): ProductImportSheet
{
    $import = ImportBuilder::import(ImportStatus::Analyzing);

    return ImportBuilder::sheet($import, 'product-export', [
        ['SKU', 'Name', 'Regular price', 'Weight (kg)', 'Date sale price starts', 'Greutate'],
        ['TB-1', 'Tricou', '1256,59', '1.250', '2026-12-01 0:00:00', '0.5'],
        ['TB-2', 'Rochie', '49,90', '0.4', '2026-12-24 23:59:59', '1,25'],
    ]);
}

/**
 * An answer for the decimal comma sheet, with the given column entries replacing the defaults.
 *
 * @param  array<int, array<string, mixed>>  $columns
 * @return array<string, mixed>
 */
function decimalCommaAnswer(array $columns = []): array
{
    $entry = fn (int $index, string $target, ?array $number = null, ?string $date = null, ?string $locale = null): array => [
        'index' => $index, 'target' => $target, 'confidence' => 0.95, 'locale' => $locale, 'option_name' => null,
        'number_format' => $number, 'date_format' => $date,
    ];

    return [
        'columns' => array_values(array_replace([
            0 => $entry(0, 'variant.sku'),
            1 => $entry(1, 'product.name', locale: 'ro'),
            2 => $entry(2, 'variant.price', ['decimal_separator' => ',', 'thousands_separator' => 'none']),
            3 => $entry(3, 'variant.weight', ['decimal_separator' => '.', 'thousands_separator' => 'none']),
            4 => $entry(4, 'ignore', null, 'Y-m-d G:i:s'),
            5 => $entry(5, 'ignore', ['decimal_separator' => '.', 'thousands_separator' => 'none']),
        ], $columns)),
        'grouping' => ['strategy' => 'per_row', 'column' => null, 'confidence' => 0.9],
        'units' => ['weight_unit' => 'kg', 'length_unit' => null],
    ];
}

test('each number and date column keeps its own format', function () {
    $mapping = app(ProposalValidator::class)->toMapping(decimalCommaAnswer(), decimalCommaSheet(), null);

    expect($mapping->column(2)->format)->toEqual(ColumnFormat::number(',', ''))
        ->and($mapping->column(2)->flagged)->toBeFalse()
        ->and($mapping->column(3)->format)->toEqual(ColumnFormat::number('.', ''))
        ->and($mapping->column(3)->flagged)->toBeFalse()
        ->and($mapping->column(4)->format)->toEqual(ColumnFormat::date('Y-m-d G:i:s'))
        ->and($mapping->column(0)->format)->toBeNull();
});

test('a number format with the same separator twice is dropped and the rest of the answer is kept', function () {
    $answer = decimalCommaAnswer([2 => [
        'index' => 2, 'target' => 'variant.price', 'confidence' => 0.95, 'locale' => null, 'option_name' => null,
        'number_format' => ['decimal_separator' => ',', 'thousands_separator' => ','], 'date_format' => null,
    ]]);

    $mapping = app(ProposalValidator::class)->toMapping($answer, decimalCommaSheet(), null);

    expect($mapping->column(2)->target)->toBe('variant.price')
        ->and($mapping->column(2)->format)->toBeNull()
        ->and($mapping->column(2)->flagged)->toBeTrue()
        ->and($mapping->column(3)->format)->toEqual(ColumnFormat::number('.', ''));
});

test('a date format is kept only when it holds date characters and separators', function (string $format, bool $kept) {
    $answer = decimalCommaAnswer([4 => [
        'index' => 4, 'target' => 'ignore', 'confidence' => 0.95, 'locale' => null, 'option_name' => null,
        'number_format' => null, 'date_format' => $format,
    ]]);

    $mapping = app(ProposalValidator::class)->toMapping($answer, decimalCommaSheet(), null);

    expect($mapping->column(4)->format?->dateFormat)->toBe($kept ? $format : null);
})->with([
    'sale date with an hour without a leading zero' => ['Y-m-d G:i:s', true],
    'escaped iso' => ['Y-m-d\TH:i:s', true],
    'hungarian' => ['Y. m. d.', true],
    'injected' => ['Y-m-d; DROP', false],
]);

test('a column whose samples do not read in its format is flagged', function () {
    $mapping = app(ProposalValidator::class)->toMapping(decimalCommaAnswer(), decimalCommaSheet(), null);

    expect($mapping->column(5)->format)->toEqual(ColumnFormat::number('.', ''))
        ->and($mapping->column(5)->flagged)->toBeTrue();
});

/**
 * A sheet whose columns use export conventions, and an answer for it with
 * the given conventions per column index.
 *
 * @param  array<int, array<string, mixed>>  $conventions
 * @return array{0: ProductImportSheet, 1: array<string, mixed>}
 */
function conventionsProposal(array $conventions): array
{
    $sheet = ImportBuilder::sheet(ImportBuilder::import(ImportStatus::Analyzing), 'export', [
        ['SKU', 'Name', 'Status', 'Backorders', 'Features', 'Images'],
        ['TB-1', 'Tricou', '2', 'notify', 'Compoziție:Bumbac 100%:1', 'https://shop.example.com/img/12-small_default.jpg'],
    ]);

    $targets = ['variant.sku', 'product.name', 'product.status', 'variant.backorders', 'product.properties', 'product.images'];

    $answer = [
        'columns' => array_map(fn (int $index, string $target): array => [
            'index' => $index, 'target' => $target, 'confidence' => 0.95, 'locale' => $target === 'product.name' ? 'ro' : null,
            'option_name' => null, 'pair' => null, 'number_format' => null, 'date_format' => null,
            'values' => null, 'item_parts' => null, 'image_suffix' => null,
            ...($conventions[$index] ?? []),
        ], array_keys($targets), $targets),
        'grouping' => ['strategy' => 'per_row', 'column' => null, 'confidence' => 0.9],
        'units' => ['weight_unit' => null, 'length_unit' => null],
    ];

    return [$sheet, $answer];
}

test('code meanings are kept under their key and a meaning that does not fit the target is dropped and flagged', function () {
    [$sheet, $answer] = conventionsProposal([
        2 => ['values' => [['value' => '2', 'meaning' => 'draft'], ['value' => ' Publicat ', 'meaning' => 'published']]],
        3 => ['values' => [['value' => 'Notify', 'meaning' => 'yes'], ['value' => '1', 'meaning' => 'published']]],
    ]);

    $mapping = app(ProposalValidator::class)->toMapping($answer, $sheet, null);

    expect($mapping->column(2)->format->values)->toBe(['2' => 'draft', 'publicat' => 'published'])
        ->and($mapping->column(2)->flagged)->toBeFalse()
        ->and($mapping->column(3)->target)->toBe('variant.backorders')
        ->and($mapping->column(3)->format->values)->toBe(['notify' => 'yes'])
        ->and($mapping->column(3)->flagged)->toBeTrue();
});

test('item parts with two names are dropped and flagged', function () {
    [$sheet, $answer] = conventionsProposal([4 => ['item_parts' => ['name', 'name']]]);
    $mapping = app(ProposalValidator::class)->toMapping($answer, $sheet, null);

    [$sheet, $valid] = conventionsProposal([4 => ['item_parts' => ['name', 'value', 'skip']]]);
    $kept = app(ProposalValidator::class)->toMapping($valid, $sheet, null);

    expect($mapping->column(4)->format)->toBeNull()
        ->and($mapping->column(4)->flagged)->toBeTrue()
        ->and($kept->column(4)->format->itemParts)->toBe(['name', 'value', 'skip'])
        ->and($kept->column(4)->flagged)->toBeFalse();
});

test('an image suffix is kept when it holds only letters, digits, dashes, and underscores', function (string $suffix, ?string $kept) {
    [$sheet, $answer] = conventionsProposal([5 => ['image_suffix' => $suffix]]);

    $mapping = app(ProposalValidator::class)->toMapping($answer, $sheet, null);

    expect($mapping->column(5)->format?->imageSuffix)->toBe($kept)
        ->and($mapping->column(5)->flagged)->toBe($kept === null);
})->with([
    'thumbnail type' => ['-small_default', '-small_default'],
    'folder' => ['../thumbs', null],
    'too long' => [str_repeat('a', 51), null],
]);

test('item parts and an image suffix on a column that does not use them are left out', function () {
    [$sheet, $answer] = conventionsProposal([0 => ['item_parts' => ['name', 'value'], 'image_suffix' => '-small']]);

    $mapping = app(ProposalValidator::class)->toMapping($answer, $sheet, null);

    expect($mapping->column(0)->format)->toBeNull()
        ->and($mapping->column(0)->flagged)->toBeFalse();
});

test('the length unit is kept only when it is one of the store units', function (?string $answered, ?string $unit) {
    $answer = decimalCommaAnswer();
    $answer['units']['length_unit'] = $answered;

    expect(app(ProposalValidator::class)->toMapping($answer, decimalCommaSheet(), null)->lengthUnit)->toBe($unit);
})->with([
    'cm from "Length (cm)"' => ['cm', 'cm'],
    'in' => ['in', 'in'],
    'centimetre is not translated' => ['centimetre', null],
    'inch is not translated' => ['inch', null],
    'no unit' => [null, null],
]);

/**
 * A sheet with list columns, and an answer for it with the given separators
 * per column index.
 *
 * @param  array<int, array<string, ?string>>  $separators
 * @return array{0: ProductImportSheet, 1: array<string, mixed>}
 */
function separatorsProposal(array $separators): array
{
    $sheet = ImportBuilder::sheet(ImportBuilder::import(ImportStatus::Analyzing), 'export', [
        ['SKU', 'Name', 'Categories', 'Options', 'Tags'],
        ['TB-1', 'Tricou', 'Women > Dresses, Women > New', 'Color:Red, Size:M', 'vara, bumbac'],
    ]);

    $targets = ['variant.sku', 'product.name', 'product.category', 'variant.options', 'product.tags'];

    $answer = [
        'columns' => array_map(fn (int $index, string $target): array => [
            'index' => $index, 'target' => $target, 'confidence' => 0.95, 'locale' => $target === 'product.name' ? 'ro' : null,
            'option_name' => null, 'pair' => null, 'number_format' => null, 'date_format' => null,
            'values' => null, 'item_parts' => null, 'image_suffix' => null,
            'list_separator' => null, 'path_separator' => null, 'item_separator' => null,
            ...($separators[$index] ?? []),
        ], array_keys($targets), $targets),
        'grouping' => ['strategy' => 'per_row', 'column' => null, 'confidence' => 0.9],
        'units' => ['weight_unit' => null, 'length_unit' => null],
    ];

    return [$sheet, $answer];
}

test('each list column keeps its own separators', function () {
    [$sheet, $answer] = separatorsProposal([
        0 => ['list_separator' => ','],
        2 => ['list_separator' => ',', 'path_separator' => ' > ', 'item_separator' => ':'],
        3 => ['list_separator' => ',', 'item_separator' => ':'],
        4 => ['list_separator' => ','],
    ]);

    $mapping = app(ProposalValidator::class)->toMapping($answer, $sheet, null);

    expect($mapping->column(2)->format)->toEqual(new ColumnFormat(listSeparator: ',', pathSeparator: '>'))
        ->and($mapping->column(2)->flagged)->toBeFalse()
        ->and($mapping->column(3)->format)->toEqual(new ColumnFormat(listSeparator: ',', itemSeparator: ':'))
        ->and($mapping->column(4)->format)->toEqual(new ColumnFormat(listSeparator: ','))
        ->and($mapping->column(0)->format)->toBeNull();
});

test('one category per cell gives none', function () {
    [$sheet, $answer] = separatorsProposal([2 => ['list_separator' => 'none', 'path_separator' => 'None']]);

    $mapping = app(ProposalValidator::class)->toMapping($answer, $sheet, null);

    expect($mapping->column(2)->format)->toEqual(new ColumnFormat(listSeparator: 'none', pathSeparator: 'none'))
        ->and($mapping->column(2)->flagged)->toBeFalse();
});

test('an unusable separator is dropped and its column flagged', function (string $separator) {
    [$sheet, $answer] = separatorsProposal([4 => ['list_separator' => $separator]]);

    $mapping = app(ProposalValidator::class)->toMapping($answer, $sheet, null);

    expect($mapping->column(4)->target)->toBe('product.tags')
        ->and($mapping->column(4)->format)->toBeNull()
        ->and($mapping->column(4)->flagged)->toBeTrue();
})->with([
    'a word' => ['and'],
    'a digit' => ['1'],
    'too long' => [' ;;;; '],
]);

test('an option name column needs a pair number and a value column of its pair', function () {
    $entry = fn (int $index, string $target, ?int $pair): array => [
        'index' => $index, 'target' => $target, 'confidence' => 0.95, 'locale' => null, 'option_name' => null,
        'number_format' => null, 'date_format' => null, 'pair' => $pair,
    ];

    $withoutPair = decimalCommaAnswer([5 => $entry(5, 'variant.option_name', null)]);
    $withoutValue = decimalCommaAnswer([5 => $entry(5, 'variant.option_name', 1)]);

    expect(fn () => app(ProposalValidator::class)->toMapping($withoutPair, decimalCommaSheet(), null))
        ->toThrow(ProposalRejected::class, 'the column "Greutate" has no pair number')
        ->and(fn () => app(ProposalValidator::class)->toMapping($withoutValue, decimalCommaSheet(), null))
        ->toThrow(ProposalRejected::class, 'pair 1 has an option name column but no option value column');
});

test('grouping by parent without a parent column is rejected', function () {
    $answer = decimalCommaAnswer();
    $answer['grouping'] = ['strategy' => 'by_parent', 'column' => null, 'confidence' => 0.9];

    expect(fn () => app(ProposalValidator::class)->toMapping($answer, decimalCommaSheet(), null))
        ->toThrow(ProposalRejected::class, 'grouping by parent needs a parent reference column');
});
