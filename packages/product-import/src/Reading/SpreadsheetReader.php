<?php

namespace Lunar\ProductImport\Reading;

use Closure;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Maatwebsite\Excel\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;

/**
 * Reads XLSX, XLS, and (prepared) CSV files: the sheet list without loading
 * cells, then one sheet at a time in chunks.
 */
class SpreadsheetReader
{
    /**
     * Rows read per chunk.
     */
    public const CHUNK_SIZE = 500;

    /**
     * The Laravel Excel reader type per file extension.
     *
     * @var array<string, string>
     */
    public const TYPES = [
        'xlsx' => Excel::XLSX,
        'xls' => Excel::XLS,
        'csv' => Excel::CSV,
    ];

    public function __construct(
        protected Excel $excel,
        protected CellValueFormatter $formatter,
    ) {}

    /**
     * List the sheets with their names and sizes, without loading cells.
     *
     * @return list<SheetInfo>
     */
    public function sheets(string $path, string $extension, string $delimiter = ','): array
    {
        $reader = IOFactory::createReader($this->readerType($extension));

        if ($reader instanceof Csv) {
            $reader->setDelimiter($delimiter);
            $reader->setInputEncoding('UTF-8');
        }

        $sheets = [];

        foreach (array_values($reader->listWorksheetInfo($path)) as $index => $info) {
            $sheets[] = new SheetInfo(
                index: $index,
                name: (string) $info['worksheetName'],
                totalRows: (int) $info['totalRows'],
                totalColumns: (int) $info['totalColumns'],
            );
        }

        return $sheets;
    }

    /**
     * Read one sheet in chunks. `$onChunk` receives each chunk's non-empty
     * rows with their file row numbers and their cells keyed by column index.
     *
     * @param  Closure(list<ReadRow>): void  $onChunk
     */
    public function read(string $path, string $extension, int $sheetIndex, Closure $onChunk, string $delimiter = ',', int $chunkSize = self::CHUNK_SIZE): void
    {
        $type = $this->readerType($extension);

        $sheetImport = $type === Excel::CSV
            ? new CsvRowsImport($this->formatter, $onChunk, $chunkSize, $delimiter)
            : new SheetRowsImport($this->formatter, $onChunk, $chunkSize, $delimiter);
        $import = $type === Excel::CSV ? $sheetImport : new SelectedSheetImport($sheetIndex, $sheetImport);

        // Cell styles are needed to tell date cells from numbers.
        $readOnly = config('excel.imports.read_only');
        config(['excel.imports.read_only' => false]);

        try {
            $this->excel->import($import, $path, null, $type);
        } finally {
            config(['excel.imports.read_only' => $readOnly]);
        }

        $sheetImport->flush();
    }

    /**
     * Read one sheet completely. For tests and small sheets.
     *
     * @return list<ReadRow>
     */
    public function readAll(string $path, string $extension, int $sheetIndex = 0, string $delimiter = ','): array
    {
        $rows = [];

        $this->read($path, $extension, $sheetIndex, function (array $chunk) use (&$rows): void {
            array_push($rows, ...$chunk);
        }, $delimiter);

        return $rows;
    }

    /**
     * The reader type for a file extension.
     */
    public function readerType(string $extension): string
    {
        return self::TYPES[Str::lower($extension)]
            ?? throw new InvalidArgumentException("Unsupported file type [{$extension}].");
    }
}
