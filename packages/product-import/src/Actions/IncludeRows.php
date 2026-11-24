<?php

namespace Lunar\ProductImport\Actions;

use Lunar\ProductImport\Actions\Concerns\GuardsImportStatus;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\RowSkipReason;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Validation\AffectedGroups;
use Lunar\ProductImport\Validation\ImportValidator;

/**
 * Includes rows staff skipped again and validates them.
 */
class IncludeRows
{
    use GuardsImportStatus;

    public function __construct(protected ImportValidator $validator) {}

    /**
     * @param  iterable<int>  $rowIds
     */
    public function handle(ProductImport $import, iterable $rowIds): int
    {
        $this->ensureStatus($import, [ImportStatus::PreviewReady]);

        $rows = ProductImportRow::includedIn($import)
            ->whereIn('id', collect($rowIds)->all())
            ->where('skip_reason', RowSkipReason::Staff)
            ->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        ProductImportRow::query()->whereIn('id', $rows->modelKeys())->update(['skip_reason' => null]);

        $this->validator->validateGroups($import, AffectedGroups::for($import, $rows));

        return $rows->count();
    }
}
