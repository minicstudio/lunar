<?php

namespace Lunar\ProductImport\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Lunar\ProductImport\Enums\ImageStatus;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Jobs\Concerns\UsesImportQueue;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportImage;
use Lunar\ProductImport\Persisting\ImageDownloader;

/**
 * Downloads a slice of the import's images, each product's in file order.
 * A failed image is recorded and never fails the job.
 */
class DownloadImages implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, UsesImportQueue;

    public int $tries = 1;

    public int $timeout = 900;

    /**
     * @param  list<int>  $imageIds
     */
    public function __construct(public int $importId, public array $imageIds)
    {
        $this->useImportQueue();
    }

    public function handle(ImageDownloader $downloader): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $import = ProductImport::query()->find($this->importId);

        if (! $import || $import->status !== ImportStatus::Importing) {
            return;
        }

        $images = ProductImportImage::query()
            ->whereIn('id', $this->imageIds)
            ->where('status', ImageStatus::Pending)
            ->orderBy('product_id')
            ->orderBy('position')
            ->get();

        foreach ($images as $image) {
            $downloader->download($image);
        }
    }
}
