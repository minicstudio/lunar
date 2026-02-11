<?php

namespace Lunar\Blog\Filament\Resources\BlogPostResource\Pages;

use Lunar\Admin\Support\Resources\Pages\ManageUrlsRelatedRecords;
use Lunar\Blog\Filament\Resources\BlogPostResource;
use Lunar\Blog\Models\BlogPost;

class ManageBlogPostUrls extends ManageUrlsRelatedRecords
{
    /**
     * The resource class for the blog post.
     */
    protected static string $resource = BlogPostResource::class;

    /**
     * The model associated with the blog post.
     */
    protected static string $model = BlogPost::class;
}
