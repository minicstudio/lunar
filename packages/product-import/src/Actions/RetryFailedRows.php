<?php

namespace Lunar\ProductImport\Actions;

use Illuminate\Support\Facades\Cache;
use Lunar\ProductImport\Actions\Concerns\GuardsImportStatus;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\RowStatus;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Persisting\CommitChain;
use Lunar\ProductImport\Structuring\RowStructurer;
use Lunar\ProductImport\Validation\ImportValidator;

/**
 * Writes the product groups with failed rows again, after staff corrected
 * them, following the same rules as the first run, and updates the result.
 */
class RetryFailedRows
{
    use GuardsImportStatus;

    public function __construct(
        protected ImportValidator $validator,
        protected RowStructurer $structurer,
        protected CommitChain $chain,
    ) {}

    public function handle(ProductImport $import): ProductImport
    {
        $finished = [ImportStatus::CompletedWithErrors, ImportStatus::Failed];

        $this->ensureStatus($import, $finished);

        if ($import->isPruned()) {
            throw ImportActionRefused::because('pruned');
        }

        $groups = ProductImportRow::includedIn($import)->where('status', RowStatus::Failed)->distinct()->pluck('group_key')->all();

        if ($groups === []) {
            throw ImportActionRefused::because('no_failed_rows');
        }

        $lock = Cache::lock(StartImport::LOCK, 30);

        if (! $lock->get()) {
            throw ImportActionRefused::because('another_import_running');
        }

        try {
            if (ProductImport::query()->whereKeyNot($import->getKey())->where('status', ImportStatus::Importing)->exists()) {
                throw ImportActionRefused::because('another_import_running');
            }

            // Validate the failed groups again with the corrections; rows that
            // still have errors stay failed.
            $this->structurer->structureGroups($import, $groups);
            $this->validator->validateGroups($import, $groups);

            ProductImportRow::includedIn($import)
                ->whereIn('group_key', $groups)
                ->where('status', RowStatus::Error)
                ->update(['status' => RowStatus::Failed->value]);

            $retry = ProductImportRow::includedIn($import)
                ->whereIn('group_key', $groups)
                ->whereIn('status', [RowStatus::Valid, RowStatus::Warning])
                ->distinct()
                ->pluck('group_key')
                ->all();

            if ($retry === []) {
                throw ImportActionRefused::because('no_failed_rows');
            }

            if (! $import->transitionTo(ImportStatus::Importing, $finished, ['finished_at' => null])) {
                throw ImportActionRefused::because('action_not_completed');
            }
        } finally {
            $lock->release();
        }

        $this->chain->dispatchRetry($import, $retry);

        return $import;
    }

    /**
     * Whether the import has failed rows that can be retried.
     */
    public function canRetry(ProductImport $import): bool
    {
        return in_array($import->status, [ImportStatus::CompletedWithErrors, ImportStatus::Failed], true)
            && ! $import->isPruned()
            && ProductImportRow::query()->where('product_import_id', $import->id)->where('status', RowStatus::Failed)->exists();
    }
}
