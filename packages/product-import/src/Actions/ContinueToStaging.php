<?php

namespace Lunar\ProductImport\Actions;

use Lunar\ProductImport\Actions\Concerns\GuardsImportStatus;
use Lunar\ProductImport\Analysis\AiAvailability;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\NewProductStatus;
use Lunar\ProductImport\Enums\SheetAnalysisStatus;
use Lunar\ProductImport\Enums\TargetField;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\ProductImport\Jobs\StageImport;
use Lunar\ProductImport\Mapping\MappingValidator;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportSheet;

/**
 * Leaves the mapping step: checks that every included sheet has a valid
 * proposal or an applied profile and a valid mapping, then starts staging.
 */
class ContinueToStaging
{
    use GuardsImportStatus;

    public function __construct(
        protected MappingValidator $validator,
        protected AiAvailability $ai,
    ) {}

    /**
     * @throws ImportActionRefused
     */
    public function handle(ProductImport $import): ProductImport
    {
        $this->ensureMappingStep($import);

        $blocker = $this->blocker($import);

        if ($blocker !== null) {
            throw new ImportActionRefused($blocker);
        }

        if (! $import->transitionTo(ImportStatus::Staging, ImportStatus::AwaitingMapping)) {
            throw ImportActionRefused::because('action_not_completed');
        }

        $this->startStaging($import);

        return $import;
    }

    /**
     * Why the import cannot leave the mapping step yet, or null when it can.
     */
    public function blocker(ProductImport $import): ?string
    {
        $sheets = $import->sheets()->where('skipped', false)->get();

        if ($sheets->isEmpty()) {
            return __('lunarpanel.product-import::errors.no_included_sheets');
        }

        foreach ($sheets as $sheet) {
            if ($sheet->analysis_status === SheetAnalysisStatus::Pending && ! $this->ai->isConfigured()) {
                return __('lunarpanel.product-import::errors.ai_not_configured');
            }

            if (! in_array($sheet->analysis_status, [SheetAnalysisStatus::Completed, SheetAnalysisStatus::ProfileApplied], true)) {
                return __('lunarpanel.product-import::errors.sheet_not_analyzed', ['sheet' => $sheet->name]);
            }

            $error = $this->mappingError($sheet);

            if ($error !== null) {
                return __('lunarpanel.product-import::errors.sheet_mapping_invalid', ['sheet' => $sheet->name, 'error' => $error]);
            }
        }

        if ($import->importOptions()->newProductStatus === NewProductStatus::FromColumn
            && ! $sheets->contains(fn (ProductImportSheet $sheet): bool => $sheet->sheetMapping()->maps(TargetField::ProductStatus))) {
            return __('lunarpanel.product-import::errors.no_status_column');
        }

        return null;
    }

    /**
     * The first problem of a sheet's mapping.
     */
    protected function mappingError(ProductImportSheet $sheet): ?string
    {
        if (! $sheet->hasMapping()) {
            return __('lunarpanel.product-import::mapping.errors.product_type_required');
        }

        return $this->validator->errors($sheet->sheetMapping(), $sheet->name)[0] ?? null;
    }

    /**
     * Queue the staging of the import's rows.
     */
    protected function startStaging(ProductImport $import): void
    {
        StageImport::dispatch($import->id);
    }
}
