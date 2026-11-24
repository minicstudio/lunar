<?php

namespace Lunar\ProductImport\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Lunar\Models\Product;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\RowStatus;
use Lunar\ProductImport\Jobs\Concerns\UsesImportQueue;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Persisting\ImportResult;
use Throwable;

/**
 * Records the import's result, ends the importing status, and updates the
 * search index once for every product the import wrote.
 */
class FinalizeImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, UsesImportQueue;

    /**
     * Products made searchable per chunk.
     */
    public const SEARCH_CHUNK = 200;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public int $importId)
    {
        $this->useImportQueue();
    }

    public function handle(ImportResult $result): void
    {
        $import = ProductImport::query()->find($this->importId);

        if (! $import || $import->status !== ImportStatus::Importing) {
            return;
        }

        // Rows a crashed job never reached count as failed, so they can be retried.
        ProductImportRow::includedIn($import)->whereIn('status', [RowStatus::Valid, RowStatus::Warning])->update([
            'status' => RowStatus::Failed->value,
            'messages' => json_encode([[
                'level' => 'error',
                'code' => 'not_processed',
                'field' => null,
                'message' => __('lunarpanel.product-import::errors.not_processed'),
            ]], JSON_UNESCAPED_UNICODE),
        ]);

        $stats = $result->stats($import);
        $status = $result->status($stats);

        $import->transitionTo($status, ImportStatus::Importing, [
            'stats' => $stats,
            'finished_at' => now(),
            'failure_message' => $status === ImportStatus::Failed ? __('lunarpanel.product-import::errors.nothing_imported') : null,
        ]);

        $productIds = $result->touchedProducts($import)->pluck('product_id')->all();

        foreach (array_chunk($productIds, self::SEARCH_CHUNK) as $chunk) {
            Product::query()->whereIn('id', $chunk)->get()->searchable();
        }
    }

    /**
     * Never leave an import importing: fail it with a message.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('Product import: finishing the import failed.', [
            'product_import_id' => $this->importId,
            'exception' => $exception,
        ]);

        ProductImport::query()->find($this->importId)?->transitionTo(ImportStatus::Failed, ImportStatus::Importing, [
            'failure_message' => __('lunarpanel.product-import::errors.finalize_failed'),
            'finished_at' => now(),
        ]);
    }
}
