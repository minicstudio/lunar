<?php

namespace Lunar\Blog\Filament\Resources\BlogCategoryResource\Pages;

use Filament\Support\Facades\FilamentIcon;
use Lunar\Admin\Support\Pages\BaseManageRelatedRecords;
use Lunar\Admin\Support\RelationManagers\ChannelRelationManager;
use Lunar\Blog\Filament\Resources\BlogCategoryResource;

class ManageBlogCategoryAvailability extends BaseManageRelatedRecords
{
    /**
     * The resource class for the blog category.
     */
    protected static string $resource = BlogCategoryResource::class;

    /**
     * The name of the relationship being managed.
     */
    protected static string $relationship = 'channels';

    /**
     * Get the title for the page.
     */
    public function getTitle(): string
    {

        return __('lunarpanel.blog::category.pages.availability.label');
    }

    /**
     * Get the navigation icon for the page.
     */
    public static function getNavigationIcon(): ?string
    {
        return FilamentIcon::resolve('lunar::availability');
    }

    /**
     * Get the navigation label for the page.
     */
    public static function getNavigationLabel(): string
    {
        return __('lunarpanel.blog::category.pages.availability.label');
    }

    /**
     * Get the relation managers for the page.
     */
    public function getRelationManagers(): array
    {
        return [
            ChannelRelationManager::class,
        ];
    }
}
