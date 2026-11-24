<?php

namespace Lunar\ProductImport\Actions;

use Lunar\ProductImport\Actions\Concerns\GuardsImportStatus;
use Lunar\ProductImport\Analysis\AnalysisCoordinator;
use Lunar\ProductImport\Analysis\SheetProfileBuilder;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\ProductImport\Models\ProductImportSheet;

/**
 * Uses another row as a sheet's header: rebuilds the sheet's columns from
 * the stored rows, clears its proposal and mapping, and analyzes it again.
 */
class ChangeHeaderRow
{
    use GuardsImportStatus;

    public function __construct(
        protected SheetProfileBuilder $profileBuilder,
        protected AnalysisCoordinator $analysis,
    ) {}

    public function handle(ProductImportSheet $sheet, int $headerRow): ProductImportSheet
    {
        $import = $sheet->import;

        $this->ensureMappingStep($import);

        if (! $sheet->rows()->where('row_number', $headerRow)->exists()) {
            throw ImportActionRefused::because('header_row_missing', ['row' => $headerRow]);
        }

        $sheet->forceFill([
            'ai_proposal' => null,
            'analysis_error' => null,
            'analysis_status' => null,
            'analysis_attempts' => 0,
            'mapping' => null,
            'offered_profile_id' => null,
            'offered_profile_score' => null,
            'profile_id' => null,
        ]);

        $this->profileBuilder->build($sheet, $headerRow);

        if (! $sheet->skipped) {
            $this->analysis->startForSheet($sheet);
        } else {
            $this->analysis->settle($import);
        }

        return $sheet->refresh();
    }
}
