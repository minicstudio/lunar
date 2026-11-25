<?php

namespace Lunar\ProductImport\Actions;

use Lunar\ProductImport\Actions\Concerns\GuardsImportStatus;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\RowRole;
use Lunar\ProductImport\Enums\RowSkipReason;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Structuring\RowStructurer;
use Lunar\ProductImport\Taxonomy\ValueCollector;
use Lunar\ProductImport\Validation\AffectedGroups;
use Lunar\ProductImport\Validation\ImportValidator;

/**
 * Includes skipped rows again and validates them: rows that staff skipped,
 * and, with a role, rows that their type skips. Rows skipped as existing
 * variants or for a skipped option value follow import-wide decisions and
 * are not included.
 */
class IncludeRows
{
    use GuardsImportStatus;

    public function __construct(
        protected ImportValidator $validator,
        protected RowStructurer $structurer,
        protected ValueCollector $collector,
        protected ContinueToPreview $preview,
    ) {}

    /**
     * Include the rows and return how many were included. A row that its type
     * skips is included only with a role, which is kept when the import is
     * staged again. When such a row brings a value that needs a decision, the
     * import returns to the value review.
     *
     * @param  iterable<int>  $rowIds
     */
    public function handle(ProductImport $import, iterable $rowIds, ?RowRole $role = null): int
    {
        $this->ensureStatus($import, [ImportStatus::PreviewReady]);

        $reasons = $role !== null ? [RowSkipReason::Staff, RowSkipReason::RowType] : [RowSkipReason::Staff];

        $rows = ProductImportRow::includedIn($import)
            ->whereIn('id', collect($rowIds)->all())
            ->whereIn('skip_reason', $reasons)
            ->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        $typeSkipped = $rows->filter(fn (ProductImportRow $row): bool => $row->skip_reason === RowSkipReason::RowType);

        ProductImportRow::query()->whereIn('id', $rows->modelKeys())->update(['skip_reason' => null]);

        if ($typeSkipped->isEmpty()) {
            $this->validator->validateGroups($import, AffectedGroups::for($import, $rows));

            return $rows->count();
        }

        ProductImportRow::query()->whereIn('id', $typeSkipped->modelKeys())->update(['role_override' => $role?->value]);

        $this->structurer->structure($import);
        $this->collector->collect($import);
        $this->validator->validate($import);

        if ($this->preview->openDecisions($import) > 0) {
            $import->transitionTo(ImportStatus::ReviewingValues, ImportStatus::PreviewReady);
        }

        return $rows->count();
    }
}
