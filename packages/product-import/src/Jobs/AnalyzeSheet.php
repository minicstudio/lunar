<?php

namespace Lunar\ProductImport\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Lunar\Models\ProductType;
use Lunar\ProductImport\Analysis\AiAvailability;
use Lunar\ProductImport\Analysis\AnalysisCoordinator;
use Lunar\ProductImport\Analysis\AnalysisFailure;
use Lunar\ProductImport\Analysis\ColumnMappingAgent;
use Lunar\ProductImport\Analysis\ProposalValidator;
use Lunar\ProductImport\Analysis\SheetDescriptionBuilder;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\SheetAnalysisStatus;
use Lunar\ProductImport\Exceptions\AiNotConfigured;
use Lunar\ProductImport\Exceptions\ProposalRejected;
use Lunar\ProductImport\Jobs\Concerns\UsesImportQueue;
use Lunar\ProductImport\Models\ProductImportSheet;
use Lunar\ProductImport\Support\LengthUnits;
use Lunar\ProductImport\Support\TargetFieldRegistry;
use Lunar\ProductImport\Support\WeightUnits;
use RuntimeException;
use Throwable;

/**
 * Asks the AI once for a sheet's column mapping, checks the answer, stores
 * it as the sheet's proposal, and prefills the mapping from it. Temporary
 * provider failures are retried by the queue; other failures are reported
 * on the sheet at once.
 */
class AnalyzeSheet implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, UsesImportQueue;

    /**
     * Attempts in total for temporary failures.
     */
    public int $tries = 3;

    public function __construct(public int $sheetId)
    {
        $this->useImportQueue();
    }

    /**
     * Seconds to wait before the next attempt.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 90];
    }

    public function handle(
        AiAvailability $ai,
        TargetFieldRegistry $registry,
        SheetDescriptionBuilder $describer,
        ProposalValidator $validator,
        AnalysisCoordinator $coordinator,
        WeightUnits $weightUnits,
        LengthUnits $lengthUnits,
    ): void {
        $sheet = ProductImportSheet::query()->find($this->sheetId);

        if (! $sheet || $sheet->skipped || $sheet->analysis_status !== SheetAnalysisStatus::Pending) {
            return;
        }

        $import = $sheet->import;

        if ($import->status !== ImportStatus::Analyzing) {
            return;
        }

        $sheet->forceFill(['analysis_attempts' => $this->attempts()])->save();

        try {
            if (! $ai->isConfigured()) {
                throw new AiNotConfigured;
            }

            $productTypeId = $this->productTypeId($sheet);

            $agent = new ColumnMappingAgent(
                $registry->targetCodes($productTypeId),
                $registry->languageCodes(),
                $weightUnits->codes(),
                $lengthUnits->codes(),
            );

            $response = $agent->prompt(json_encode(
                $describer->build($sheet, $productTypeId),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ));

            if (! $response instanceof StructuredAgentResponse) {
                throw ProposalRejected::invalid(__('lunarpanel.product-import::analysis.reasons.no_columns'));
            }

            $answer = $response->toArray();
            $mapping = $validator->toMapping($answer, $sheet, $productTypeId);
        } catch (Throwable $exception) {
            $failure = AnalysisFailure::fromThrowable($exception);

            if ($failure->isRetryable() && $this->attempts() < $this->tries) {
                throw $exception;
            }

            $this->failNow($exception);

            return;
        }

        if (! $import->stillHasStatus(ImportStatus::Analyzing)) {
            return;
        }

        $sheet->forceFill([
            'ai_proposal' => $answer,
            'analysis_status' => SheetAnalysisStatus::Completed,
            'analysis_error' => null,
            'mapping' => $mapping->toArray(),
        ])->save();

        // The proposal belongs to its sheet: the import options never change.
        $import->forceFill([
            'ai_provider' => $ai->provider(),
            'ai_model' => $response->meta->model ?? $ai->model(),
        ])->save();

        $coordinator->settle($import);
    }

    /**
     * Report the failure on the sheet, without the API key, and log it.
     */
    public function failed(?Throwable $exception): void
    {
        $sheet = ProductImportSheet::query()->find($this->sheetId);

        if (! $sheet) {
            return;
        }

        $failure = AnalysisFailure::fromThrowable($exception ?? new RuntimeException('The analysis failed.'));

        Log::warning('Product import: the AI analysis of a sheet failed.', [
            'product_import_id' => $sheet->product_import_id,
            'product_import_sheet_id' => $sheet->id,
            'code' => $failure->code,
            'raw' => $failure->raw,
        ]);

        $sheet->forceFill([
            'analysis_status' => SheetAnalysisStatus::Failed,
            'analysis_error' => $failure->toArray(max(1, (int) $sheet->analysis_attempts)),
        ])->save();

        app(AnalysisCoordinator::class)->settle($sheet->import);
    }

    /**
     * Fail without further attempts, also when run outside a queue worker.
     */
    protected function failNow(Throwable $exception): void
    {
        if ($this->job !== null) {
            $this->fail($exception);

            return;
        }

        $this->failed($exception);
    }

    /**
     * The sheet's product type, or the first one for a new mapping.
     */
    protected function productTypeId(ProductImportSheet $sheet): ?int
    {
        return $sheet->sheetMapping()->productTypeId
            ?? ProductType::query()->orderBy('id')->value('id');
    }
}
