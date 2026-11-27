<?php

use Lunar\ProductImport\Reading\CsvPreparer;
use Lunar\Tests\ProductImport\Support\SpreadsheetFixtures;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class);

afterEach(fn () => SpreadsheetFixtures::cleanup());

/**
 * Parse a prepared CSV copy into rows.
 *
 * @return list<list<string>>
 */
function parsePreparedCsv(string $path, string $delimiter): array
{
    $rows = [];
    $handle = fopen($path, 'r');

    while (($row = fgetcsv($handle, null, $delimiter, '"', '\\')) !== false) {
        $rows[] = $row;
    }

    fclose($handle);

    return $rows;
}

test('a semicolon csv with decimal commas keeps the decimal comma in one cell', function () {
    $path = SpreadsheetFixtures::csv([
        ['Cod', 'Denumire', 'Pret'],
        ['TB-001-S', 'Tricou basic', '49,90'],
        ['TB-001-M', 'Tricou basic', '129,00'],
    ], ';');

    $prepared = app(CsvPreparer::class)->prepare($path);
    $rows = parsePreparedCsv($prepared->path, $prepared->delimiter);
    $prepared->cleanup();

    expect($prepared->delimiter)->toBe(';')
        ->and($rows[1])->toBe(['TB-001-S', 'Tricou basic', '49,90'])
        ->and($rows[2][2])->toBe('129,00');
});

test('the delimiter is detected among comma, semicolon, and tab', function (string $delimiter) {
    $path = SpreadsheetFixtures::csv([
        ['SKU', 'Name', 'Price', 'Stock'],
        ['A-1', 'Shirt, red', '10.5', '3'],
        ['A-2', 'Shirt; blue', '11.5', '4'],
    ], $delimiter);

    expect(app(CsvPreparer::class)->prepare($path)->delimiter)->toBe($delimiter);
})->with([
    'comma' => [','],
    'semicolon' => [';'],
    'tab' => ["\t"],
]);

test('windows-1250 input with windows-1250 chosen is converted to utf-8', function () {
    $path = SpreadsheetFixtures::csv([
        ['Denumire', 'Megnevezés'],
        ['Rochie roşie', 'Hosszú ujjú póló'],
    ], ';', 'WINDOWS-1250');

    expect(mb_check_encoding(file_get_contents($path), 'UTF-8'))->toBeFalse();

    $prepared = app(CsvPreparer::class)->prepare($path, 'Windows-1250');
    $rows = parsePreparedCsv($prepared->path, $prepared->delimiter);
    $prepared->cleanup();

    expect($prepared->converted)->toBeTrue()
        ->and($rows[0])->toBe(['Denumire', 'Megnevezés'])
        ->and($rows[1])->toBe(['Rochie roşie', 'Hosszú ujjú póló']);
});

test('iso-8859-16 input with iso-8859-16 chosen keeps the comma-below letters', function () {
    $path = SpreadsheetFixtures::csv([['Denumire'], ['Rochie roșie'], ['Țesătură']], ';', 'ISO-8859-16');

    $prepared = app(CsvPreparer::class)->prepare($path, 'ISO-8859-16');
    $rows = parsePreparedCsv($prepared->path, $prepared->delimiter);
    $prepared->cleanup();

    expect($rows[1])->toBe(['Rochie roșie'])
        ->and($rows[2])->toBe(['Țesătură']);
});

test('utf-8 input with another encoding chosen stays unchanged', function () {
    $path = SpreadsheetFixtures::csv([['Denumire', 'Megnevezés'], ['Rochie roșie', 'Hosszú ujjú póló']], ';');

    $prepared = app(CsvPreparer::class)->prepare($path, 'Windows-1250');
    $rows = parsePreparedCsv($prepared->path, $prepared->delimiter);
    $prepared->cleanup();

    expect($prepared->converted)->toBeFalse()
        ->and($rows[1])->toBe(['Rochie roșie', 'Hosszú ujjú póló']);
});

test('input that is not utf-8 is refused when no other encoding is chosen', function (?string $encoding) {
    $path = SpreadsheetFixtures::csv([['Megnevezés'], ['Hosszú ujjú póló']], ';', 'WINDOWS-1250');

    expect(fn () => app(CsvPreparer::class)->prepare($path, $encoding))
        ->toThrow(RuntimeException::class, 'not valid UTF-8');
})->with([
    'no encoding' => [null],
    'utf-8' => ['UTF-8'],
]);

test('encodings are compared without case and unknown ones are not recognized', function () {
    expect(CsvPreparer::canonicalEncoding('windows-1250'))->toBe('Windows-1250')
        ->and(CsvPreparer::canonicalEncoding(' iso-8859-16 '))->toBe('ISO-8859-16')
        ->and(CsvPreparer::canonicalEncoding('KOI8-R'))->toBeNull()
        ->and(CsvPreparer::canonicalEncoding(''))->toBeNull()
        ->and(CsvPreparer::canonicalEncoding(null))->toBeNull();
});

test('a utf-8 byte order mark is stripped', function () {
    $path = SpreadsheetFixtures::csv([['SKU', 'Name'], ['A-1', 'Shirt']], ',', bom: true);

    $prepared = app(CsvPreparer::class)->prepare($path);
    $content = file_get_contents($prepared->path);
    $prepared->cleanup();

    expect($prepared->converted)->toBeFalse()
        ->and(str_starts_with($content, 'SKU'))->toBeTrue();
});
