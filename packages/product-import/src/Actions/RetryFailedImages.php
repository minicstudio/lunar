<?php

namespace Lunar\ProductImport\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Lunar\ProductImport\Actions\Concerns\GuardsImportStatus;
use Lunar\ProductImport\Enums\ImageStatus;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportImage;
use Lunar\ProductImport\Persisting\CommitChain;

/**
 * Downloads the failed images of a completed import again, by the rules of
 * the first run, after staff corrected their URLs or not. Like a start, it
 * takes the global commit lock, because the import is importing until the
 * downloads end. It writes no product, variant, or row; the result counts
 * the images again and keeps the status the rows give.
 */
class RetryFailedImages
{
    use GuardsImportStatus;

    /**
     * The statuses a retry of failed images starts from.
     *
     * @var list<ImportStatus>
     */
    public const FROM = [ImportStatus::Completed, ImportStatus::CompletedWithErrors];

    public function __construct(protected CommitChain $chain) {}

    public function handle(ProductImport $import): ProductImport
    {
        $this->ensureStatus($import, self::FROM);

        if ($import->isPruned()) {
            throw ImportActionRefused::because('pruned');
        }

        if (! $this->failedImages($import)->exists()) {
            throw ImportActionRefused::because('no_failed_images');
        }

        $lock = Cache::lock(StartImport::LOCK, 30);

        if (! $lock->get()) {
            throw ImportActionRefused::because('another_import_running');
        }

        try {
            if (ProductImport::query()->whereKeyNot($import->getKey())->where('status', ImportStatus::Importing)->exists()) {
                throw ImportActionRefused::because('another_import_running');
            }

            if (! $import->transitionTo(ImportStatus::Importing, self::FROM, ['finished_at' => null])) {
                throw ImportActionRefused::because('action_not_completed');
            }

            $this->failedImages($import)->update(['status' => ImageStatus::Pending->value, 'error' => null]);
        } finally {
            $lock->release();
        }

        $this->chain->dispatchImageRetry($import);

        return $import;
    }

    /**
     * Whether the import has failed images that can be retried.
     */
    public function canRetry(ProductImport $import): bool
    {
        return in_array($import->status, self::FROM, true)
            && ! $import->isPruned()
            && $this->failedImages($import)->exists();
    }

    /**
     * The import's failed images.
     *
     * @return Builder<ProductImportImage>
     */
    protected function failedImages(ProductImport $import): Builder
    {
        return ProductImportImage::query()
            ->where('product_import_id', $import->id)
            ->where('status', ImageStatus::Failed);
    }
}
