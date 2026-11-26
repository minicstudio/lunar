<?php

namespace Lunar\ProductImport\Actions;

use Lunar\ProductImport\Actions\Concerns\GuardsImportStatus;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\ProductImport\Models\ProductImport;

/**
 * Leaves the value review once every similar value has a decision.
 */
class ContinueToPreview
{
    use GuardsImportStatus;

    public function handle(ProductImport $import): ProductImport
    {
        $this->ensureStatus($import, [ImportStatus::ReviewingValues], 'not_review_step');

        $open = $this->openDecisions($import);

        if ($open > 0) {
            throw new ImportActionRefused(trans_choice('lunarpanel.product-import::errors.open_decisions', $open, ['count' => $open]));
        }

        if (! $import->transitionTo(ImportStatus::PreviewReady, ImportStatus::ReviewingValues)) {
            throw ImportActionRefused::because('action_not_completed');
        }

        return $import;
    }

    /**
     * How many values still need a decision.
     */
    public function openDecisions(ProductImport $import): int
    {
        return $import->values()->whereNull('action')->count();
    }
}
