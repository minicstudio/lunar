<?php

namespace Lunar\Review\Generators;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class ReviewPathGenerator implements PathGenerator
{
    /**
     * Get the path for the original media files.
     */
    public function getPath(Media $media): string
    {
        $basePath = $this->getBasePath($media);

        return "{$basePath}originals/";
    }

    /**
     * Get the path for media conversions.
     */
    public function getPathForConversions(Media $media): string
    {
        $basePath = $this->getBasePath($media);

        return "{$basePath}conversions/";
    }

    /**
     * Get the path for responsive images.
     */
    public function getPathForResponsiveImages(Media $media): string
    {
        $basePath = $this->getBasePath($media);

        return "{$basePath}responsive/";
    }

    /**
     * Get the base path based on the reviewable entity.
     */
    protected function getBasePath(Media $media): string
    {
        $review = $media->model;
        $entity = $review->reviewable_type;
        $entityId = $review->reviewable_id;

        return "reviews/{$entity}/{$entityId}/";
    }
}
