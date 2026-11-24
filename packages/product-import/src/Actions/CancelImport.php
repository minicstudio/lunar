<?php

namespace Lunar\ProductImport\Actions;

use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\ProductImport\Models\ProductImport;

/**
 * Cancels an import in any status before importing, also while it is read,
 * analyzed, or staged: running jobs check the status and stop. The catalog
 * is not changed.
 */
class CancelImport
{
    public function handle(ProductImport $import): ProductImport
    {
        $import->refresh();

        if (! $import->status->isCancellable()) {
            throw ImportActionRefused::because('not_cancellable');
        }

        if (! $import->transitionTo(ImportStatus::Cancelled, ImportStatus::cancellable(), ['finished_at' => now()])) {
            throw ImportActionRefused::because('not_cancellable');
        }

        return $import;
    }
}
