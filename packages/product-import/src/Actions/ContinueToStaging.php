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
 * Leaves the mapping step: checks that every included sheet has a mapping
 * (the AI's proposal, an applied profile, or one staff made by hand) and
 * that it is valid, then starts staging.
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

        if (! $import->transitionTo(ImportStatus::Staging, [ImportStatus::AwaitingMapping, ImportStatus::AnalysisFailed])) {
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

            if (! $this->isMapped($sheet)) {
                return __('lunarpanel.product-import::errors.sheet_not_mapped', ['sheet' => $sheet->name]);
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
     * Whether a sheet has a mapping to stage: the AI's proposal, an applied
     * profile, or one staff made by hand, also after a failed analysis or
     * instead of an offered profile. A sheet whose analysis still runs has
     * none yet, because the proposal will replace it.
     */
    protected function isMapped(ProductImportSheet $sheet): bool
    {
        return $sheet->analysis_status !== SheetAnalysisStatus::Pending && $sheet->hasMapping();
    }

    /**
     * The first problem of a sheet's mapping.
     */
    protected function mappingError(ProductImportSheet $sheet): ?string
    {
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
