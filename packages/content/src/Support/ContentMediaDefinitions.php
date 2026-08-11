<?php

namespace Lunar\Content\Support;

use Lunar\Base\StandardMediaDefinitions;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ContentMediaDefinitions extends StandardMediaDefinitions
{
    /**
     * Register the media collections for content blocks.
     */
    public function registerMediaCollections(HasMedia $model): void
    {
        $collection = $model->addMediaCollection('heroes')
            ->singleFile()
            ->useDisk(config('lunar.content.upload_disk'));

        $this->registerCollectionConversions($collection, $model);
    }

    /**
     * Hero conversions are registered on the collection only.
     */
    public function registerMediaConversions(HasMedia $model, ?Media $media = null): void
    {
        //
    }

    /**
     * Register the media conversions for hero images.
     */
    protected function registerCollectionConversions(MediaCollection $collection, HasMedia $model): void
    {
        $collection->registerMediaConversions(function (Media $media) use ($model) {
            $model->addMediaConversion('small')
                ->fit(Fit::Max, 300, 300)
                ->nonQueued();

            $model->addMediaConversion('large')
                ->fit(Fit::Max, 1920, 1080)
                ->nonQueued();
        });
    }

    /**
     * Get media collection titles.
     */
    public function getMediaCollectionTitles(): array
    {
        return [
            'heroes' => __('lunarpanel.content::hero.media.collection_title'),
        ];
    }

    /**
     * Get media collection descriptions.
     */
    public function getMediaCollectionDescriptions(): array
    {
        return [
            'heroes' => __('lunarpanel.content::hero.media.collection_description'),
        ];
    }
}
