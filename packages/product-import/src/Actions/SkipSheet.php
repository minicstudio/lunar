<?php

namespace Lunar\ProductImport\Actions;

use Lunar\ProductImport\Actions\Concerns\GuardsImportStatus;
use Lunar\ProductImport\Analysis\AnalysisCoordinator;
use Lunar\ProductImport\Models\ProductImportSheet;

/**
 * Leaves a sheet out of the analysis, staging, and import.
 */
class SkipSheet
{
    use GuardsImportStatus;

    public function __construct(protected AnalysisCoordinator $analysis) {}

    public function handle(ProductImportSheet $sheet): ProductImportSheet
    {
        $import = $sheet->import;

        $this->ensureMappingStep($import);

        $sheet->forceFill(['skipped' => true])->save();

        $this->analysis->settle($import);

        return $sheet;
    }
}
