<?php

namespace Lunar\ProductImport\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Lunar\ProductImport\Analysis\AnalysisCoordinator;
use Lunar\ProductImport\Analysis\SheetProfileBuilder;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\RowStatus;
use Lunar\ProductImport\Exceptions\TooManyRows;
use Lunar\ProductImport\Jobs\Concerns\UsesImportQueue;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportFile;
use Lunar\ProductImport\Models\ProductImportSheet;
use Lunar\ProductImport\Reading\CsvPreparer;
use Lunar\ProductImport\Reading\HeaderDetector;
use Lunar\ProductImport\Reading\PreparedCsv;
use Lunar\ProductImport\Reading\ReadRow;
use Lunar\ProductImport\Reading\SpreadsheetReader;
use RuntimeException;
use Throwable;

/**
 * Reads every sheet of every uploaded file once, in upload order, into the
 * staging rows, builds each sheet's header row and column profiles, and
 * moves the import on to the analysis.
 */
class ReadImportFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, UsesImportQueue;

    /**
     * Reading is not retried: a file that cannot be read fails the import.
     */
    public int $tries = 1;

    /**
     * Large workbooks take a while to read.
     */
    public int $timeout = 900;

    public function __construct(public int $importId)
    {
        $this->useImportQueue();
    }

    public function handle(
        SpreadsheetReader $reader,
        CsvPreparer $csvPreparer,
        SheetProfileBuilder $profileBuilder,
        AnalysisCoordinator $analysis,
    ): void {
        $import = ProductImport::query()->find($this->importId);

        if (! $import || $import->status !== ImportStatus::Uploaded) {
            return;
        }

        $maxRows = (int) config('lunar.product-import.max_rows', 20000);
        $files = $import->files()->get();
        $storedRows = 0;
        $position = 0;

        try {
            foreach ($files as $file) {
                if (! $this->readFile($import, $file, $files->count() > 1, $reader, $csvPreparer, $storedRows, $position, $maxRows)) {
                    return;
                }
            }
        } catch (TooManyRows) {
            $this->failForRowLimit($import, $maxRows);

            return;
        }

        foreach ($import->sheets()->get() as $sheet) {
            $profileBuilder->build($sheet);
        }

        $dataRows = (int) $import->sheets()->where('skipped', false)->sum('row_count');

        if ($dataRows > $maxRows) {
            $this->failForRowLimit($import, $maxRows);

            return;
        }

        if (! $import->transitionTo(ImportStatus::Analyzing, ImportStatus::Uploaded)) {
            return;
        }

        $analysis->startForImport($import);
    }

    /**
     * Read every sheet of one file into the staging rows. `$storedRows`
     * counts the rows of all files, so `max_rows` applies to the import.
     * Returns false when the import is no longer uploaded.
     *
     * @throws TooManyRows
     */
    protected function readFile(
        ProductImport $import,
        ProductImportFile $file,
        bool $severalFiles,
        SpreadsheetReader $reader,
        CsvPreparer $csvPreparer,
        int &$storedRows,
        int &$position,
        int $maxRows,
    ): bool {
        $extension = $file->extension();
        $localPath = $this->copyToLocal($file);
        $prepared = null;

        try {
            $path = $localPath;
            $delimiter = ',';

            if ($file->isCsv()) {
                $prepared = $csvPreparer->prepare($localPath, $file->encoding);
                $path = $prepared->path;
                $delimiter = $prepared->delimiter;
            }

            $infos = $reader->sheets($path, $extension, $delimiter);

            // A CSV file has no formatting, so its line count is exact.
            if ($file->isCsv() && $storedRows + ($infos[0]->totalRows ?? 0) - HeaderDetector::CANDIDATE_ROWS * ($position + 1) > $maxRows) {
                throw new TooManyRows;
            }

            foreach ($infos as $info) {
                if (! $import->stillHasStatus(ImportStatus::Uploaded)) {
                    return false;
                }

                $sheet = $import->sheets()->create([
                    'product_import_file_id' => $file->id,
                    'position' => $position,
                    'name' => $this->sheetName($file, $info->name, $severalFiles),
                    'header_row' => 1,
                    'row_count' => 0,
                    'skipped' => false,
                ]);

                $position++;
                $allowance = $position * HeaderDetector::CANDIDATE_ROWS;

                $reader->read($path, $extension, $info->index, function (array $rows) use ($sheet, &$storedRows, $maxRows, $allowance): void {
                    $this->storeRows($sheet, $rows);

                    $storedRows += count($rows);

                    if ($storedRows - $allowance > $maxRows) {
                        throw new TooManyRows;
                    }
                }, $delimiter);
            }
        } finally {
            $this->cleanup($localPath, $prepared);
        }

        return true;
    }

    /**
     * Fail the import when the file cannot be read.
     */
    public function failed(?Throwable $exception): void
    {
        $import = ProductImport::query()->find($this->importId);

        Log::error('Product import: the file could not be read.', [
            'product_import_id' => $this->importId,
            'exception' => $exception,
        ]);

        $import?->transitionTo(ImportStatus::Failed, ImportStatus::Uploaded, [
            'failure_message' => __('lunarpanel.product-import::errors.unreadable_file'),
            'finished_at' => now(),
        ]);
    }

    /**
     * Insert one chunk of rows.
     *
     * @param  list<ReadRow>  $rows
     */
    protected function storeRows(ProductImportSheet $sheet, array $rows): void
    {
        $records = array_map(fn (ReadRow $row): array => [
            'product_import_id' => $sheet->product_import_id,
            'product_import_sheet_id' => $sheet->id,
            'row_number' => $row->rowNumber,
            'raw' => json_encode((object) $row->cells, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'typed' => json_encode($row->typed),
            'status' => RowStatus::Pending->value,
        ], $rows);

        foreach (array_chunk($records, 250) as $batch) {
            $sheet->rows()->getQuery()->insert($batch);
        }
    }

    /**
     * Fail the import, naming the row limit, and drop what was staged.
     */
    protected function failForRowLimit(ProductImport $import, int $maxRows): void
    {
        $import->rows()->delete();

        $import->transitionTo(ImportStatus::Failed, ImportStatus::Uploaded, [
            'failure_message' => __('lunarpanel.product-import::errors.too_many_rows', [
                'limit' => Number::format($maxRows, locale: 'en'),
            ]),
            'finished_at' => now(),
        ]);
    }

    /**
     * Copy a stored file to a local temporary file the readers can open.
     */
    protected function copyToLocal(ProductImportFile $file): string
    {
        $stream = Storage::disk($file->disk)->readStream((string) $file->path);

        if (! is_resource($stream)) {
            throw new RuntimeException('The uploaded file is missing.');
        }

        $base = tempnam(sys_get_temp_dir(), 'product-import-');
        @unlink($base);
        $localPath = $base.'.'.$file->extension();

        $target = fopen($localPath, 'wb');
        stream_copy_to_stream($stream, $target);
        fclose($target);
        fclose($stream);

        return $localPath;
    }

    /**
     * The name of a sheet: a CSV file's name, or a worksheet's name, after its
     * file's name when the import has several files.
     */
    protected function sheetName(ProductImportFile $file, string $worksheet, bool $severalFiles): string
    {
        $name = match (true) {
            $file->isCsv() => $file->original_name ?: 'CSV',
            $severalFiles => __('lunarpanel.product-import::import.file_sheet', ['file' => $file->original_name, 'sheet' => $worksheet]),
            default => $worksheet,
        };

        return Str::limit($name, 255, '');
    }

    /**
     * Delete the temporary copies.
     */
    protected function cleanup(string $localPath, ?PreparedCsv $prepared): void
    {
        $prepared?->cleanup();

        if (is_file($localPath)) {
            @unlink($localPath);
        }
    }
}
