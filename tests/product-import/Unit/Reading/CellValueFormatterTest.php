<?php

use Lunar\ProductImport\Reading\CellValueFormatter;
use Lunar\Tests\ProductImport\Support\SpreadsheetFixtures;
use Lunar\Tests\ProductImport\TestCase;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(TestCase::class);

afterEach(fn () => SpreadsheetFixtures::cleanup());

/**
 * Write one row of cells to an XLSX file, read it back, and format each cell.
 *
 * @param  list<mixed>  $cells
 * @return list<?string>
 */
function formatCellsThroughXlsx(array $cells): array
{
    $path = SpreadsheetFixtures::xlsx(['Sheet' => [1 => ['a', 'b', 'c', 'd', 'e', 'f'], 2 => $cells]]);

    $worksheet = IOFactory::createReader('Xlsx')->load($path)->getSheet(0);
    $formatter = app(CellValueFormatter::class);

    $values = [];

    foreach (range(1, count($cells)) as $column) {
        $values[] = $formatter->format($worksheet->getCell([$column, 2]));
    }

    return $values;
}

test('an integral number becomes its digits without an exponent', function () {
    expect(formatCellsThroughXlsx([5941234567890])[0])->toBe('5941234567890');
});

test('a text code keeps its leading zeros', function () {
    expect(formatCellsThroughXlsx([SpreadsheetFixtures::text('000123')])[0])->toBe('000123');
});

test('a formula cell gives its calculated value', function () {
    expect(formatCellsThroughXlsx([20, SpreadsheetFixtures::formula('=A2*2')])[1])->toBe('40');
});

test('a date cell gives an iso 8601 date', function () {
    expect(formatCellsThroughXlsx([SpreadsheetFixtures::date('2026-11-18')])[0])->toBe('2026-11-18');
});

test('decimals keep their precision', function () {
    expect(formatCellsThroughXlsx([49.9, 0.1 + 0.2, 1249.9, -15.25])[0])->toBe('49.9')
        ->and(app(CellValueFormatter::class)->formatValue(0.1 + 0.2))->toBe('0.3')
        ->and(app(CellValueFormatter::class)->formatValue(1249.9))->toBe('1249.9')
        ->and(app(CellValueFormatter::class)->formatValue(-15.25))->toBe('-15.25')
        ->and(app(CellValueFormatter::class)->formatValue(0.000123))->toBe('0.000123')
        ->and(app(CellValueFormatter::class)->formatValue(1.0E+15))->toBe('1000000000000000');
});

test('text is kept unchanged and empty cells give null', function () {
    $formatter = app(CellValueFormatter::class);

    expect($formatter->formatValue('  Tricou basic '))->toBe('  Tricou basic ')
        ->and($formatter->formatValue(''))->toBeNull()
        ->and($formatter->formatValue(null))->toBeNull()
        ->and($formatter->formatValue(true))->toBe('TRUE');
});
