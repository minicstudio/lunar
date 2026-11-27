<?php

namespace Lunar\ProductImport\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Jobs\Concerns\UsesImportQueue;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Persisting\CommitContext;
use Lunar\ProductImport\Persisting\ProductUpserter;
use Throwable;

/**
 * Writes a slice of the import's product groups, one transaction per group.
 * A group that fails is rolled back and its rows are marked as failed; the
 * other groups go on.
 */
class CommitProductGroups implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, UsesImportQueue;

    public int $tries = 1;

    public int $timeout = 900;

    /**
     * @param  list<string>  $groupKeys
     */
    public function __construct(public int $importId, public array $groupKeys)
    {
        $this->useImportQueue();
    }

    public function handle(ProductUpserter $upserter): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $import = ProductImport::query()->find($this->importId);

        if (! $import || $import->status !== ImportStatus::Importing) {
            return;
        }

        $context = CommitContext::for($import);

        foreach ($this->groupKeys as $groupKey) {
            try {
                $upserter->upsert($import, $groupKey, $context);
            } catch (Throwable $exception) {
                Log::warning('Product import: a product group failed.', [
                    'product_import_id' => $import->id,
                    'group_key' => $groupKey,
                    'exception' => $exception,
                ]);

                $upserter->markFailed($import, $groupKey, $exception);
            }
        }
    }
}
