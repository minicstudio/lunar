<?php

namespace Lunar\ProductImport\Actions;

use Lunar\ProductImport\Actions\Concerns\GuardsImportStatus;
use Lunar\ProductImport\Analysis\AiAvailability;
use Lunar\ProductImport\Analysis\AnalysisCoordinator;
use Lunar\ProductImport\Enums\SheetAnalysisStatus;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\ProductImport\Models\ProductImportSheet;

/**
 * Analyzes one failed sheet again. Other sheets keep their results.
 */
class RetrySheetAnalysis
{
    use GuardsImportStatus;

    public function __construct(
        protected AnalysisCoordinator $analysis,
        protected AiAvailability $ai,
    ) {}

    public function handle(ProductImportSheet $sheet): ProductImportSheet
    {
        $this->ensureMappingStep($sheet->import);

        if ($sheet->skipped || $sheet->analysis_status !== SheetAnalysisStatus::Failed) {
            throw ImportActionRefused::because('nothing_to_retry');
        }

        if (! $this->ai->isConfigured()) {
            throw ImportActionRefused::because('ai_not_configured');
        }

        $this->analysis->startForSheet($sheet);

        return $sheet->refresh();
    }
}
