<?php

namespace Lunar\Tests\ProductImport\Support;

use DateTimeImmutable;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Builds XLSX, XLS, and CSV files at test time, so no binary fixture is
 * committed.
 *
 * A sheet is a list of rows keyed by file row number (1-based) or a plain
 * list (row 1 first). A row is a list of cells. A cell is a scalar, null for
 * an empty cell, or one of the helpers below for typed cells.
 */
class SpreadsheetFixtures
{
    /**
     * Files written during the test, deleted by `cleanup()`.
     *
     * @var list<string>
     */
    protected static array $files = [];

    /**
     * A text cell, kept as text even when it looks like a number.
     *
     * @return array{type: string, value: string}
     */
    public static function text(string $value): array
    {
        return ['type' => 'text', 'value' => $value];
    }

    /**
     * A date cell: an Excel serial number with a date format.
     *
     * @return array{type: string, value: string}
     */
    public static function date(string $isoDate): array
    {
        return ['type' => 'date', 'value' => $isoDate];
    }

    /**
     * A formula cell, for example `=B2*2`.
     *
     * @return array{type: string, value: string}
     */
    public static function formula(string $formula): array
    {
        return ['type' => 'formula', 'value' => $formula];
    }

    /**
     * Write an XLSX workbook.
     *
     * @param  array<string, array<int, list<mixed>>>  $sheets  Sheet name => rows.
     */
    public static function xlsx(array $sheets): string
    {
        $path = static::path('xlsx');

        (new Xlsx(static::spreadsheet($sheets)))->save($path);

        return $path;
    }

    /**
     * Write an XLS (BIFF) workbook.
     *
     * @param  array<string, array<int, list<mixed>>>  $sheets  Sheet name => rows.
     */
    public static function xls(array $sheets): string
    {
        $path = static::path('xls');

        (new Xls(static::spreadsheet($sheets)))->save($path);

        return $path;
    }

    /**
     * Write a CSV file, optionally in another encoding and with a BOM.
     *
     * @param  array<int, list<?string>>  $rows
     */
    public static function csv(array $rows, string $delimiter = ',', ?string $encoding = null, bool $bom = false): string
    {
        $handle = fopen('php://temp', 'w+');

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn ($cell) => $cell === null ? '' : (string) $cell, $row), $delimiter, '"', '\\');
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        if ($encoding !== null) {
            $content = iconv('UTF-8', $encoding, $content);
        }

        if ($bom) {
            $content = "\xEF\xBB\xBF".$content;
        }

        $path = static::path('csv');
        file_put_contents($path, $content);

        return $path;
    }

    /**
     * Build `$count` rows from a row builder taking the 1-based data row index.
     *
     * @param  callable(int): list<mixed>  $row
     * @return list<list<mixed>>
     */
    public static function rows(int $count, callable $row): array
    {
        return array_map($row, range(1, $count));
    }

    /**
     * Delete every file written during the test.
     */
    public static function cleanup(): void
    {
        foreach (static::$files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        static::$files = [];
    }

    /**
     * Build the spreadsheet in memory.
     *
     * @param  array<string, array<int, list<mixed>>>  $sheets
     */
    protected static function spreadsheet(array $sheets): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        foreach ($sheets as $name => $rows) {
            $worksheet = $spreadsheet->createSheet();
            $worksheet->setTitle($name);

            $isList = array_is_list($rows);

            foreach ($rows as $key => $cells) {
                $rowNumber = $isList ? $key + 1 : $key;

                foreach (array_values($cells) as $index => $cell) {
                    if ($cell === null) {
                        continue;
                    }

                    $coordinate = Coordinate::stringFromColumnIndex($index + 1).$rowNumber;

                    if (is_array($cell)) {
                        static::writeTypedCell($worksheet, $coordinate, $cell);

                        continue;
                    }

                    $worksheet->setCellValue($coordinate, $cell);
                }
            }
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * Write a text, date, or formula cell.
     *
     * @param  array{type: string, value: string}  $cell
     */
    protected static function writeTypedCell(mixed $worksheet, string $coordinate, array $cell): void
    {
        match ($cell['type']) {
            'text' => $worksheet->setCellValueExplicit($coordinate, $cell['value'], DataType::TYPE_STRING),
            'date' => $worksheet->setCellValue($coordinate, Date::PHPToExcel(new DateTimeImmutable($cell['value'])))
                ->getStyle($coordinate)->getNumberFormat()->setFormatCode('yyyy-mm-dd'),
            'formula' => $worksheet->setCellValue($coordinate, $cell['value']),
        };
    }

    /**
     * A new temporary file path with the given extension.
     */
    protected static function path(string $extension): string
    {
        $base = tempnam(sys_get_temp_dir(), 'product-import-fixture-');
        @unlink($base);

        $path = $base.'.'.$extension;
        static::$files[] = $path;

        return $path;
    }
}
