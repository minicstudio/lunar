<?php

namespace Lunar\ProductImport\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportImage;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Models\ProductImportValue;
use Throwable;

/**
 * Deletes the staged rows, collected values, image records, and uploaded
 * files of imports past their retention, and cancels imports that were
 * never started. The import record, its statistics, sheets, mappings, and
 * AI proposals stay.
 */
class PruneProductImports extends Command
{
    /**
     * @var string
     */
    protected $signature = 'product-import:prune';

    /**
     * @var string
     */
    protected $description = 'Remove the staged rows and files of old product imports';

    /**
     * Rows and values deleted per query.
     */
    protected const CHUNK = 1000;

    public function handle(): int
    {
        $retention = now()->subDays((int) config('lunar.product-import.retention_days', 30));
        $abandoned = now()->subDays((int) config('lunar.product-import.abandoned_days', 7));

        $pruned = 0;

        $this->prunable($retention, $abandoned)->chunkById(50, function ($imports) use (&$pruned): void {
            foreach ($imports as $import) {
                $this->prune($import);
                $pruned++;
            }
        });

        $this->info("Pruned {$pruned} product imports.");

        return self::SUCCESS;
    }

    /**
     * Finished imports past the retention period, cancelled imports past the
     * abandoned period, and imports never started within the abandoned period.
     *
     * @return Builder<ProductImport>
     */
    protected function prunable($retention, $abandoned): Builder
    {
        $finished = [ImportStatus::Completed->value, ImportStatus::CompletedWithErrors->value, ImportStatus::Failed->value];
        $unstarted = array_map(fn (ImportStatus $status): string => $status->value, array_filter(
            ImportStatus::cancellable(),
            fn (ImportStatus $status): bool => ! $status->isBusy(),
        ));

        return ProductImport::query()
            ->whereNull('pruned_at')
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query->whereIn('status', $finished)->where('finished_at', '<', $retention))
                ->orWhere(fn (Builder $query) => $query->where('status', ImportStatus::Cancelled->value)->where(
                    fn (Builder $query) => $query->where('finished_at', '<', $abandoned)->orWhere(fn (Builder $query) => $query->whereNull('finished_at')->where('created_at', '<', $abandoned)),
                ))
                ->orWhere(fn (Builder $query) => $query->whereIn('status', array_values($unstarted))->where('created_at', '<', $abandoned)));
    }

    /**
     * Prune one import.
     */
    protected function prune(ProductImport $import): void
    {
        if ($import->status->isCancellable()) {
            $import->transitionTo(ImportStatus::Cancelled, ImportStatus::cancellable(), ['finished_at' => $import->finished_at ?? now()]);
        }

        do {
            $ids = ProductImportImage::query()->where('product_import_id', $import->id)->limit(self::CHUNK)->pluck('id');
            ProductImportImage::query()->whereIn('id', $ids)->delete();
        } while ($ids->isNotEmpty());

        do {
            $ids = ProductImportRow::query()->where('product_import_id', $import->id)->limit(self::CHUNK)->pluck('id');
            ProductImportRow::query()->whereIn('id', $ids)->delete();
        } while ($ids->isNotEmpty());

        do {
            $ids = ProductImportValue::query()->where('product_import_id', $import->id)->limit(self::CHUNK)->pluck('id');
            ProductImportValue::query()->whereIn('id', $ids)->delete();
        } while ($ids->isNotEmpty());

        foreach ($import->files()->whereNotNull('path')->get() as $file) {
            try {
                Storage::disk($file->disk)->delete((string) $file->path);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $import->forceFill(['pruned_at' => now()])->save();
    }
}
