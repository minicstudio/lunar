<?php

namespace Lunar\ProductImport\Reading;

use Closure;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Row;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * The Laravel Excel import of one sheet: collects each chunk's non-empty
 * rows and hands them over when the chunk's sheet import ends.
 */
class SheetRowsImport implements Import, OnEachRow, SkipsEmptyRows, WithChunkReading, WithCustomCsvSettings, WithEvents
{
    /**
     * The rows of the chunk being read.
     *
     * @var list<ReadRow>
     */
    protected array $buffer = [];

    /**
     * @param  Closure(list<ReadRow>): void  $onChunk
     */
    public function __construct(
        protected CellValueFormatter $formatter,
        protected Closure $onChunk,
        protected int $chunkSize,
        protected string $delimiter = ',',
    ) {}

    /**
     * Collect one row with its file row number.
     */
    public function onRow(Row $row): void
    {
        $iterator = $row->getDelegate()->getCellIterator();
        $iterator->setIterateOnlyExistingCells(true);

        $cells = [];
        $typed = [];

        foreach ($iterator as $cell) {
            [$value, $isTyped] = $this->formatter->read($cell);

            if ($value === null || Str::trim($value) === '') {
                continue;
            }

            $index = Coordinate::columnIndexFromString($cell->getColumn()) - 1;
            $cells[$index] = $value;

            if ($isTyped) {
                $typed[] = $index;
            }
        }

        if ($cells === []) {
            return;
        }

        ksort($cells);
        sort($typed);

        $this->buffer[] = new ReadRow($row->getIndex(), $cells, $typed);
    }

    /**
     * Hand over the chunk's rows once the chunk is read.
     *
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => fn () => $this->flush(),
        ];
    }

    /**
     * Pass the buffered rows on and start a new chunk.
     */
    public function flush(): void
    {
        if ($this->buffer === []) {
            return;
        }

        $rows = $this->buffer;
        $this->buffer = [];

        ($this->onChunk)($rows);
    }

    public function chunkSize(): int
    {
        return $this->chunkSize;
    }

    /**
     * The prepared CSV copy is UTF-8 with a detected delimiter.
     *
     * @return array<string, mixed>
     */
    public function getCsvSettings(): array
    {
        return [
            'delimiter' => $this->delimiter,
            'input_encoding' => 'UTF-8',
        ];
    }
}
