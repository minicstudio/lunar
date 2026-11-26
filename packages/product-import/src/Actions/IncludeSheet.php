<?php

namespace Lunar\ProductImport\Actions;

use Lunar\ProductImport\Actions\Concerns\GuardsImportStatus;
use Lunar\ProductImport\Analysis\AnalysisCoordinator;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\ProductImport\Models\ProductImportSheet;

/**
 * Includes a skipped sheet again. A sheet that was never analyzed gets its
 * analysis now.
 */
class IncludeSheet
{
    use GuardsImportStatus;

    public function __construct(protected AnalysisCoordinator $analysis) {}

    public function handle(ProductImportSheet $sheet): ProductImportSheet
    {
        $import = $sheet->import;

        $this->ensureMappingStep($import);

        if ($sheet->row_count === 0) {
            throw ImportActionRefused::because('sheet_without_rows');
        }

        $sheet->forceFill(['skipped' => false])->save();

        if ($sheet->analysis_status === null) {
            $this->analysis->startForSheet($sheet);
        } else {
            $this->analysis->settle($import);
        }

        return $sheet;
    }
}
