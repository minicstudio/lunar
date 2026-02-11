<?php

namespace Lunar\Blog\Observers;

use Illuminate\Support\Facades\Storage;
use Lunar\Blog\Models\BlogPost;

class BlogPostObserver
{
    /**
     * Handle the BlogPost "updated" event.
     */
    public function updated(BlogPost $blogPost): void
    {
        // If the blog post's thumbnail image was updated and differs from the old,
        // delete the old thumbnail from the designated storage disk.
        $currentThumbnail = $blogPost->getThumbnail();

        $originalAttributes = $blogPost->getOriginal('attribute_data');
        $thumbnailField = $originalAttributes['thumbnail'] ?? null;
        $thumbnailValues = $thumbnailField?->getValue() ?? [];
        $originalThumbnail = collect($thumbnailValues)->first();

        if ($originalThumbnail && $originalThumbnail !== $currentThumbnail) {
            Storage::disk($blogPost->thumbnailDisk())->delete($originalThumbnail);
        }
    }

    /**
     * Handle the BlogPost "deleted" event.
     */
    public function deleting(BlogPost $blogPost): void
    {
        // If the blog post has an associated thumbnail image, delete it from the designated storage disk.
        $thumbnail = $blogPost->getThumbnail();

        if ($thumbnail) {
            Storage::disk($blogPost->thumbnailDisk())->delete($thumbnail);
        }
    }
}
