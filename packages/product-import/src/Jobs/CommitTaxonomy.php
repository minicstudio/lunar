<?php

namespace Lunar\ProductImport\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Jobs\Concerns\UsesImportQueue;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Persisting\TaxonomyCreator;
use Throwable;

/**
 * Creates the brands, categories, options, and option values staff decided
 * to create, before any product is written. When it fails, nothing is
 * written and the import fails.
 */
class CommitTaxonomy implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, UsesImportQueue;

    public int $tries = 1;

    public function __construct(public int $importId)
    {
        $this->useImportQueue();
    }

    public function handle(TaxonomyCreator $creator): void
    {
        $import = ProductImport::query()->find($this->importId);

        if (! $import || $import->status !== ImportStatus::Importing) {
            return;
        }

        $created = $creator->create($import);

        $stats = $import->stats ?? [];
        $stats['created'] = $created;
        $import->forceFill(['stats' => $stats])->save();
    }

    /**
     * Fail the import; the chain stops, so no product is written.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('Product import: creating categories, brands, and options failed.', [
            'product_import_id' => $this->importId,
            'exception' => $exception,
        ]);

        ProductImport::query()->find($this->importId)?->transitionTo(ImportStatus::Failed, ImportStatus::Importing, [
            'failure_message' => __('lunarpanel.product-import::errors.taxonomy_failed'),
            'finished_at' => now(),
        ]);
    }
}
