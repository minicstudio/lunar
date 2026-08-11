<?php

namespace Lunar\Console\Commands;

use Illuminate\Console\Command;
use Lunar\Jobs\Media\ConvertMediaToWebp as ConvertMediaToWebpJob;
use Lunar\Models\Brand;
use Lunar\Models\Collection;
use Lunar\Models\Product;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ConvertMediaToWebp extends Command
{
    /**
     * @var string
     */
    protected $signature = 'lunar:media:convert-webp
        {--ids=* : Only convert the given media IDs}
        {--only-missing : Only regenerate missing conversions after converting the original}
        {--starting-from-id= : Convert media with an id equal to or higher than the provided value}
        {--chunk=100 : Number of media records to load per chunk when dispatching jobs}';

    /**
     * @var string
     */
    protected $description = 'Dispatch jobs to convert media that is not yet WebP';

    /**
     * @var list<class-string>
     */
    protected array $modelTypes = [
        Product::class,
        Brand::class,
        Collection::class,
    ];

    /**
     * @var list<string>
     */
    protected array $conversions = [
        'small',
        'medium',
        'large',
        'zoom',
    ];

    /**
     * Dispatch WebP conversion jobs for media that is not yet WebP.
     */
    public function handle(): int
    {
        $dispatched = 0;
        $onlyMissing = (bool) $this->option('only-missing');
        $chunkSize = max(1, (int) $this->option('chunk'));

        foreach ($this->modelTypes as $modelClass) {
            $modelType = $modelClass::morphName();

            $this->info("Dispatching WebP conversion jobs for [{$modelType}]...");

            $query = Media::query()
                ->where('model_type', $modelType)
                ->where(function ($query): void {
                    $query->where('mime_type', '!=', 'image/webp')
                        ->orWhereNull('mime_type');
                })
                ->orderBy('id');

            if ($ids = $this->option('ids')) {
                $query->whereIn('id', $ids);
            }

            if ($startingFromId = $this->option('starting-from-id')) {
                $query->where('id', '>=', $startingFromId);
            }

            $query->chunkById($chunkSize, function ($mediaItems) use (&$dispatched, $onlyMissing): void {
                foreach ($mediaItems as $media) {
                    ConvertMediaToWebpJob::dispatch(
                        $media,
                        $onlyMissing,
                        $this->conversions,
                    );

                    $dispatched++;
                }
            });
        }

        $this->info("Dispatched {$dispatched} WebP conversion job(s) for media not yet converted.");
        $this->comment('Run a queue worker to process them, e.g. php artisan queue:work');

        return self::SUCCESS;
    }
}
