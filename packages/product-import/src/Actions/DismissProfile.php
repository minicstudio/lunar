<?php

namespace Lunar\ProductImport\Actions;

use Lunar\ProductImport\Actions\Concerns\GuardsImportStatus;
use Lunar\ProductImport\Analysis\AiAvailability;
use Lunar\ProductImport\Analysis\AnalysisCoordinator;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\ProductImport\Models\ProductImportSheet;

/**
 * Declines the offered profile and asks the AI for the sheet's mapping instead.
 */
class DismissProfile
{
    use GuardsImportStatus;

    public function __construct(
        protected AnalysisCoordinator $analysis,
        protected AiAvailability $ai,
    ) {}

    public function handle(ProductImportSheet $sheet): ProductImportSheet
    {
        $this->ensureMappingStep($sheet->import);

        if (! $this->ai->isConfigured()) {
            throw ImportActionRefused::because('ai_not_configured');
        }

        $sheet->forceFill(['offered_profile_id' => null, 'offered_profile_score' => null])->save();

        $this->analysis->startForSheet($sheet);

        return $sheet->refresh();
    }
}
