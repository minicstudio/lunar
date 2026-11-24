<?php

namespace Lunar\ProductImport\Actions;

use Lunar\ProductImport\Actions\Concerns\GuardsImportStatus;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\ProductImport\Models\ProductImport;

/**
 * Steps back from the preview to the value review.
 */
class ReturnToValueReview
{
    use GuardsImportStatus;

    public function handle(ProductImport $import): ProductImport
    {
        $this->ensureStatus($import, [ImportStatus::PreviewReady]);

        if (! $import->values()->exists()) {
            throw ImportActionRefused::because('no_values');
        }

        if (! $import->transitionTo(ImportStatus::ReviewingValues, ImportStatus::PreviewReady)) {
            throw ImportActionRefused::because('action_not_completed');
        }

        return $import;
    }
}
