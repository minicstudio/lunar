<?php

use Lunar\ProductImport\Reading\ReadRow;
use Lunar\ProductImport\Reading\SpreadsheetReader;
use Lunar\Tests\ProductImport\Support\SpreadsheetFixtures;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class);

afterEach(fn () => SpreadsheetFixtures::cleanup());

/**
 * Rows keyed by row number with their cells.
 *
 * @param  list<ReadRow>  $rows
 * @return array<int, array<int, string>>
 */
function rowsByNumber(array $rows): array
{
    $byNumber = [];

    foreach ($rows as $row) {
        $byNumber[$row->rowNumber] = $row->cells;
    }

    return $byNumber;
}

test('an xlsx workbook with three sheets is listed and each sheet is read', function () {
    $path = SpreadsheetFixtures::xlsx([
        'Produse' => [['SKU', 'Denumire', 'Pret'], ['TB-001-S', 'Tricou basic', '49,90'], ['TB-001-M', 'Tricou basic', 49.9]],
        'Outlet' => [['SKU', 'Denumire'], ['OUT-1', 'Geacă puf']],
        'Info' => [['Exportat la 18.11.2026']],
    ]);

    $reader = app(SpreadsheetReader::class);
    $sheets = $reader->sheets($path, 'xlsx');

    expect(array_map(fn ($sheet) => $sheet->name, $sheets))->toBe(['Produse', 'Outlet', 'Info'])
        ->and($sheets[0]->totalRows)->toBe(3);

    expect(rowsByNumber($reader->readAll($path, 'xlsx', 0)))->toBe([
        1 => ['SKU', 'Denumire', 'Pret'],
        2 => ['TB-001-S', 'Tricou basic', '49,90'],
        3 => ['TB-001-M', 'Tricou basic', '49.9'],
    ])->and(rowsByNumber($reader->readAll($path, 'xlsx', 1)))->toBe([
        1 => ['SKU', 'Denumire'],
        2 => ['OUT-1', 'Geacă puf'],
    ])->and(rowsByNumber($reader->readAll($path, 'xlsx', 2)))->toBe([
        1 => ['Exportat la 18.11.2026'],
    ]);
});

test('an xls workbook is read', function () {
    $path = SpreadsheetFixtures::xls([
        'Produse' => [['SKU', 'EAN'], ['TB-001-S', 5941234567890], ['TB-001-M', SpreadsheetFixtures::text('000123')]],
    ]);

    $reader = app(SpreadsheetReader::class);

    expect($reader->sheets($path, 'xls')[0]->name)->toBe('Produse')
        ->and(rowsByNumber($reader->readAll($path, 'xls')))->toBe([
            1 => ['SKU', 'EAN'],
            2 => ['TB-001-S', '5941234567890'],
            3 => ['TB-001-M', '000123'],
        ]);
});

test('a csv file is read with its delimiter', function () {
    $path = SpreadsheetFixtures::csv([
        ['Cod', 'Denumire', 'Pret'],
        ['TB-001-S', 'Tricou basic', '49,90'],
    ], ';');

    $reader = app(SpreadsheetReader::class);

    expect($reader->sheets($path, 'csv', ';'))->toHaveCount(1)
        ->and(rowsByNumber($reader->readAll($path, 'csv', 0, ';')))->toBe([
            1 => ['Cod', 'Denumire', 'Pret'],
            2 => ['TB-001-S', 'Tricou basic', '49,90'],
        ]);
});

test('date cells are read as iso dates', function () {
    $path = SpreadsheetFixtures::xlsx([
        'Produse' => [['SKU', 'Data'], ['TB-001-S', SpreadsheetFixtures::date('2026-11-18')]],
    ]);

    expect(rowsByNumber(app(SpreadsheetReader::class)->readAll($path, 'xlsx'))[2])->toBe(['TB-001-S', '2026-11-18']);
});

test('an empty row is not yielded and the next row keeps its number', function () {
    $rows = [1 => ['SKU', 'Pret']];

    foreach (range(2, 12) as $number) {
        if ($number !== 10) {
            $rows[$number] = ["SKU-{$number}", '10'];
        }
    }

    $path = SpreadsheetFixtures::xlsx(['Produse' => $rows]);

    $read = rowsByNumber(app(SpreadsheetReader::class)->readAll($path, 'xlsx'));

    expect($read)->not->toHaveKey(10)
        ->and($read[11])->toBe(['SKU-11', '10'])
        ->and($read)->toHaveCount(11);
});

test('cells are keyed by column index and empty cells are left out', function () {
    $path = SpreadsheetFixtures::xlsx([
        'Produse' => [['SKU', null, 'Pret'], ['TB-001-S', null, '49,90']],
    ]);

    expect(rowsByNumber(app(SpreadsheetReader::class)->readAll($path, 'xlsx'))[2])->toBe([0 => 'TB-001-S', 2 => '49,90']);
});

test('a 1,200-row sheet is read in three chunks', function () {
    $path = SpreadsheetFixtures::xlsx([
        'Produse' => [['SKU', 'Pret'], ...SpreadsheetFixtures::rows(1200, fn (int $i) => ["SKU-{$i}", $i])],
    ]);

    $chunks = [];

    app(SpreadsheetReader::class)->read($path, 'xlsx', 0, function (array $rows) use (&$chunks): void {
        $chunks[] = count($rows);
    });

    expect($chunks)->toBe([500, 500, 201]);
});
