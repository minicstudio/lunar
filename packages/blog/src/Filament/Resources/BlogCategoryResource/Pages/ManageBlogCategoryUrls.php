<?php

namespace Lunar\Blog\Filament\Resources\BlogCategoryResource\Pages;

use Lunar\Admin\Support\Resources\Pages\ManageUrlsRelatedRecords;
use Lunar\Blog\Filament\Resources\BlogCategoryResource;
use Lunar\Blog\Models\BlogCategory;

class ManageBlogCategoryUrls extends ManageUrlsRelatedRecords
{
    /**
     * The resource class for the blog category.
     */
    protected static string $resource = BlogCategoryResource::class;

    /**
     * The model associated with the blog category.
     */
    protected static string $model = BlogCategory::class;
}
