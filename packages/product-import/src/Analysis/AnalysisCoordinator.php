<?php

namespace Lunar\ProductImport\Analysis;

use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\SheetAnalysisStatus;
use Lunar\ProductImport\Jobs\AnalyzeSheet;
use Lunar\ProductImport\Mapping\ProfileMatcher;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportSheet;

/**
 * Starts the per-sheet analysis of an import and moves the import on once
 * every included sheet has a result.
 */
class AnalysisCoordinator
{
    public function __construct(protected ProfileMatcher $profiles) {}

    /**
     * Start the analysis of every included sheet of a freshly read import. A
     * sheet whose headers match a saved profile gets that profile offered and
     * no analysis while the offer stands.
     */
    public function startForImport(ProductImport $import): void
    {
        $sheets = $import->sheets()->where('skipped', false)->get()
            ->reject(fn (ProductImportSheet $sheet): bool => $this->offerProfile($sheet))
            ->values();

        // Mark every sheet first, so a finished sheet never sees the others as done.
        foreach ($sheets as $sheet) {
            $this->markPending($sheet);
        }

        foreach ($sheets as $sheet) {
            $this->dispatch($sheet);
        }

        $this->settle($import);
    }

    /**
     * Analyze one sheet again (after a header row change, a retry, or a
     * dismissed profile). The import is analyzing until the sheet has a result.
     */
    public function startForSheet(ProductImportSheet $sheet): void
    {
        $import = $sheet->import;

        if ($import->status !== ImportStatus::Analyzing) {
            $import->transitionTo(ImportStatus::Analyzing, [ImportStatus::AwaitingMapping, ImportStatus::AnalysisFailed]);
        }

        $this->markPending($sheet);
        $this->dispatch($sheet);
    }

    /**
     * Move the import on once no included sheet waits for its analysis: to
     * analysis failed while an included sheet failed, else to awaiting mapping.
     */
    public function settle(ProductImport $import): void
    {
        $import->refresh();

        $statuses = $import->sheets()->where('skipped', false)->pluck('analysis_status');

        if ($statuses->contains(SheetAnalysisStatus::Pending)) {
            return;
        }

        $from = [ImportStatus::Uploaded, ImportStatus::Analyzing, ImportStatus::AnalysisFailed, ImportStatus::AwaitingMapping];

        if ($statuses->contains(SheetAnalysisStatus::Failed)) {
            if ($import->status !== ImportStatus::AnalysisFailed) {
                $import->transitionTo(ImportStatus::AnalysisFailed, $from);
            }

            return;
        }

        if ($import->status !== ImportStatus::AwaitingMapping) {
            $import->transitionTo(ImportStatus::AwaitingMapping, $from);
        }
    }

    /**
     * Offer the best matching profile, if any. Returns whether one was offered.
     */
    protected function offerProfile(ProductImportSheet $sheet): bool
    {
        $match = $this->profiles->best(array_column($sheet->columns ?? [], 'header'));

        if ($match === null) {
            return false;
        }

        $sheet->forceFill([
            'offered_profile_id' => $match->profile->id,
            'offered_profile_score' => $match->score,
            'analysis_status' => SheetAnalysisStatus::ProfileOffered,
        ])->save();

        return true;
    }

    /**
     * Mark a sheet as waiting for its analysis.
     */
    protected function markPending(ProductImportSheet $sheet): void
    {
        $sheet->forceFill([
            'analysis_status' => SheetAnalysisStatus::Pending,
            'analysis_error' => null,
            'analysis_attempts' => 0,
        ])->save();
    }

    /**
     * Queue the analysis job of a sheet.
     */
    protected function dispatch(ProductImportSheet $sheet): void
    {
        AnalyzeSheet::dispatch($sheet->id);
    }
}
