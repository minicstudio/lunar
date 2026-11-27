<?php

namespace Lunar\ProductImport\Persisting;

use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Lunar\ProductImport\Enums\ImageStatus;
use Lunar\ProductImport\Enums\RowStatus;
use Lunar\ProductImport\Jobs\CommitAssociations;
use Lunar\ProductImport\Jobs\CommitProductGroups;
use Lunar\ProductImport\Jobs\CommitTaxonomy;
use Lunar\ProductImport\Jobs\DownloadImages;
use Lunar\ProductImport\Jobs\FinalizeImport;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportImage;
use Lunar\ProductImport\Models\ProductImportRow;

/**
 * Queues the write to the catalog: the taxonomy first, then the product
 * groups in a batch that allows failures, then the up-sells and
 * cross-sells, then the image downloads in a batch that allows failures
 * (queued by the association step, once the images are known), then the
 * result. A retry of failed images queues only the image downloads and the
 * result.
 */
class CommitChain
{
    /**
     * Queue the chain for the import's importable groups.
     */
    public function dispatch(ProductImport $import): void
    {
        $this->dispatchFor($import, $this->importableGroups($import), withTaxonomy: true);
    }

    /**
     * Queue the given groups again, with their associations and images, then
     * the result. Used to retry failed rows.
     *
     * @param  list<string>  $groupKeys
     */
    public function dispatchRetry(ProductImport $import, array $groupKeys): void
    {
        $this->dispatchFor($import, $groupKeys, withTaxonomy: false);
    }

    /**
     * Queue the download of the import's pending images, then the result.
     * Used to retry failed images: no product, variant, or row is written.
     */
    public function dispatchImageRetry(ProductImport $import): void
    {
        $this->dispatchChain(array_values(array_filter([
            $this->imageBatch($import),
            new FinalizeImport($import->id),
        ])));
    }

    /**
     * The groups with at least one row to import.
     *
     * @return list<string>
     */
    public function importableGroups(ProductImport $import): array
    {
        return ProductImportRow::includedIn($import)
            ->whereIn('status', [RowStatus::Valid, RowStatus::Warning])
            ->distinct()
            ->orderBy('group_key')
            ->pluck('group_key')
            ->all();
    }

    /**
     * A batch downloading the import's pending images, `images.per_job` per
     * job without splitting a product's images, or null when there are none.
     * On a retry, the failed images of the retried groups are tried again.
     *
     * @param  ?list<string>  $groupKeys
     */
    public function imageBatch(ProductImport $import, ?array $groupKeys = null): ?PendingBatch
    {
        if ($groupKeys !== null) {
            ProductImportImage::query()
                ->where('product_import_id', $import->id)
                ->where('status', ImageStatus::Failed)
                ->whereIn('product_import_row_id', ProductImportRow::includedIn($import)->whereIn('group_key', $groupKeys)->select('id'))
                ->update(['status' => ImageStatus::Pending->value, 'error' => null]);
        }

        $images = ProductImportImage::query()
            ->where('product_import_id', $import->id)
            ->where('status', ImageStatus::Pending)
            ->orderBy('product_id')
            ->orderBy('position')
            ->get(['id', 'product_id']);

        if ($images->isEmpty()) {
            return null;
        }

        $perJob = max(1, (int) config('lunar.product-import.images.per_job', 20));
        $jobs = [];
        $slice = [];

        foreach ($images->groupBy('product_id') as $productImages) {
            if ($slice !== [] && count($slice) + $productImages->count() > $perJob) {
                $jobs[] = new DownloadImages($import->id, $slice);
                $slice = [];
            }

            array_push($slice, ...$productImages->pluck('id')->map(fn ($id): int => (int) $id)->all());
        }

        if ($slice !== []) {
            $jobs[] = new DownloadImages($import->id, $slice);
        }

        return $this->onImportQueue(Bus::batch($jobs)->name('product-import-'.$import->id.'-images')->allowFailures());
    }

    /**
     * @param  list<string>  $groupKeys
     */
    protected function dispatchFor(ProductImport $import, array $groupKeys, bool $withTaxonomy): void
    {
        $perJob = max(1, (int) config('lunar.product-import.commit.groups_per_job', 25));

        $jobs = (new Collection($groupKeys))
            ->chunk($perJob)
            ->map(fn (Collection $keys): CommitProductGroups => new CommitProductGroups($import->id, $keys->values()->all()))
            ->values()
            ->all();

        $batch = $this->onImportQueue(Bus::batch($jobs)
            ->name('product-import-'.$import->id)
            ->allowFailures());

        $this->dispatchChain(array_values(array_filter([
            $withTaxonomy ? new CommitTaxonomy($import->id) : null,
            $batch,
            new CommitAssociations($import->id, $withTaxonomy ? null : $groupKeys),
            new FinalizeImport($import->id),
        ])));
    }

    /**
     * Queue jobs and batches one after another on the import queue.
     *
     * @param  list<mixed>  $jobs
     */
    protected function dispatchChain(array $jobs): void
    {
        $chain = Bus::chain($jobs);

        if ($queue = config('lunar.product-import.queue.name')) {
            $chain->onQueue($queue);
        }

        if ($connection = config('lunar.product-import.queue.connection')) {
            $chain->onConnection($connection);
        }

        $chain->dispatch();
    }

    /**
     * Put a batch on the configured import queue and connection.
     */
    protected function onImportQueue(PendingBatch $batch): PendingBatch
    {
        if ($queue = config('lunar.product-import.queue.name')) {
            $batch->onQueue($queue);
        }

        if ($connection = config('lunar.product-import.queue.connection')) {
            $batch->onConnection($connection);
        }

        return $batch;
    }
}
