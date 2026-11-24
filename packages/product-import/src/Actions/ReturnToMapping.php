<?php

namespace Lunar\ProductImport\Actions;

use Lunar\ProductImport\Actions\Concerns\GuardsImportStatus;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\ProductImport\Models\ProductImport;

/**
 * Steps back from the value review or the preview to the mapping. Row
 * corrections and value decisions are kept for the next staging.
 */
class ReturnToMapping
{
    use GuardsImportStatus;

    public function handle(ProductImport $import): ProductImport
    {
        $from = [ImportStatus::ReviewingValues, ImportStatus::PreviewReady];

        $this->ensureStatus($import, $from);

        if (! $import->transitionTo(ImportStatus::AwaitingMapping, $from)) {
            throw ImportActionRefused::because('action_not_completed');
        }

        return $import;
    }
}
