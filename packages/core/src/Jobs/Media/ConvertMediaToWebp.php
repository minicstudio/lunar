<?php

namespace Lunar\Jobs\Media;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Lunar\Base\MediaWebpConverter;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ConvertMediaToWebp implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Delete the job if the media model no longer exists.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create a new job instance.
     *
     * @param  list<string>  $onlyConversions  Conversions to regenerate, empty regenerates every registered conversion.
     */
    public function __construct(
        public Media $media,
        public bool $onlyMissing = false,
        public array $onlyConversions = [],
    ) {
        if ($queue = config('media-library.queue_name')) {
            $this->onQueue($queue);
        }

        if ($connection = config('media-library.queue_connection_name')) {
            $this->onConnection($connection);
        }
    }

    /**
     * Convert the media original to WebP and regenerate derived conversions.
     */
    public function handle(FileManipulator $fileManipulator): void
    {
        $media = $this->media->fresh();

        if (! $media) {
            return;
        }

        try {
            $converted = MediaWebpConverter::convertMediaOriginal($media);
        } catch (\RuntimeException $exception) {
            report($exception);

            return;
        }

        if (! $converted) {
            return;
        }

        $media = $media->fresh() ?? $media;

        $fileManipulator->createDerivedFiles(
            $media,
            $this->onlyConversions,
            $this->onlyMissing,
        );
    }
}
