<?php

namespace Lunar\ProductImport\Actions\Concerns;

use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\ProductImport\Models\ProductImport;

/**
 * Refuses changes while background work runs or outside the step they belong to.
 */
trait GuardsImportStatus
{
    /**
     * Read the stored status again and refuse unless it is one of the given ones.
     *
     * @param  list<ImportStatus>  $allowed
     *
     * @throws ImportActionRefused
     */
    protected function ensureStatus(ProductImport $import, array $allowed, string $refusal = 'wrong_step'): void
    {
        $import->refresh();

        if (in_array($import->status, $allowed, true)) {
            return;
        }

        if ($import->status->isBusy()) {
            throw ImportActionRefused::because('busy');
        }

        throw ImportActionRefused::because($refusal);
    }

    /**
     * Refuse unless the import is at the mapping step.
     *
     * @throws ImportActionRefused
     */
    protected function ensureMappingStep(ProductImport $import): void
    {
        $this->ensureStatus($import, [ImportStatus::AnalysisFailed, ImportStatus::AwaitingMapping], 'not_mapping_step');
    }
}
