<?php

namespace Lunar\ProductImport\Actions;

use Illuminate\Support\Collection;
use Lunar\ProductImport\Actions\Concerns\GuardsImportStatus;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\RowSkipReason;
use Lunar\ProductImport\Enums\RowStatus;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Validation\AffectedGroups;
use Lunar\ProductImport\Validation\ImportValidator;

/**
 * Skips single rows, a selection, or every row with errors. Skipped rows
 * are not imported; the groups they belong to are validated again.
 */
class SkipRows
{
    use GuardsImportStatus;

    public function __construct(protected ImportValidator $validator) {}

    /**
     * @param  iterable<int>  $rowIds
     */
    public function handle(ProductImport $import, iterable $rowIds): int
    {
        $this->ensureStatus($import, [ImportStatus::PreviewReady]);

        return $this->skip($import, ProductImportRow::includedIn($import)->whereIn('id', collect($rowIds)->all())->get());
    }

    /**
     * Skip every row that has errors.
     */
    public function errors(ProductImport $import): int
    {
        $this->ensureStatus($import, [ImportStatus::PreviewReady]);

        return $this->skip($import, ProductImportRow::includedIn($import)->where('status', RowStatus::Error)->get());
    }

    /**
     * Mark the rows as skipped by staff and validate their groups again.
     *
     * @param  Collection<int, ProductImportRow>  $rows
     */
    public function skip(ProductImport $import, Collection $rows): int
    {
        if ($rows->isEmpty()) {
            return 0;
        }

        ProductImportRow::query()->whereIn('id', $rows->modelKeys())->update([
            'skip_reason' => RowSkipReason::Staff->value,
            'status' => RowStatus::Skipped->value,
        ]);

        $this->validator->validateGroups($import, AffectedGroups::for($import, $rows));

        return $rows->count();
    }
}
