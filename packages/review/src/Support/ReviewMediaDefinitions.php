<?php

namespace Lunar\Review\Support;

use Lunar\Base\StandardMediaDefinitions;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ReviewMediaDefinitions extends StandardMediaDefinitions
{
    /**
     * Register the media collections for reviews.
     */
    public function registerMediaCollections(HasMedia $model): void
    {
        $collection = $model->addMediaCollection('reviews')
            ->useDisk(config('lunar.review.upload_disk'));

        $this->registerCollectionConversions($collection, $model);
    }

    /**
     * Register the media conversions for reviews.
     */
    protected function registerCollectionConversions(MediaCollection $collection, HasMedia $model): void
    {
        $conversions = [
            'small' => [
                'width' => 124,
                'height' => 92,
            ],
            'full' => [
                'width' => 1920,
                'height' => 1920,
            ],
        ];

        $collection->registerMediaConversions(function (Media $media) use ($model, $conversions) {
            foreach ($conversions as $key => $conversion) {
                $model->addMediaConversion($key)
                    ->fit(
                        Fit::Crop,
                        $conversion['width'],
                        $conversion['height']
                    )
                    ->nonQueued();
            }
        });
    }

    /**
     * Get media collection titles.
     */
    public function getMediaCollectionTitles(): array
    {
        return [
            'reviews' => __('Review Images'),
        ];
    }

    /**
     * Get media collection descriptions.
     */
    public function getMediaCollectionDescriptions(): array
    {
        return [
            'reviews' => __('Images attached to reviews'),
        ];
    }
}
