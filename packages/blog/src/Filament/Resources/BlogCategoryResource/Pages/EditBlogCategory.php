<?php

namespace Lunar\Blog\Filament\Resources\BlogCategoryResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Support\Facades\FilamentIcon;
use Illuminate\Contracts\Support\Htmlable;
use Lunar\Admin\Support\Pages\BaseEditRecord;
use Lunar\Blog\Filament\Resources\BlogCategoryResource;

class EditBlogCategory extends BaseEditRecord
{
    /**
     * The resource class for the blog category.
     */
    protected static string $resource = BlogCategoryResource::class;

    /**
     * Get the title for the page.
     *
     * @return string
     */
    public function getTitle(): string|Htmlable
    {
        return __('lunarpanel.blog::category.edit.label');
    }

    /**
     * Get the navigation label for the page.
     */
    public static function getNavigationLabel(): string
    {
        return __('lunarpanel.blog::category.edit.label');
    }

    /**
     * Get the navigation icon for the page.
     */
    public static function getNavigationIcon(): ?string
    {
        return FilamentIcon::resolve('lunar::basic-information');
    }

    /**
     * Get the default header actions for the page.
     */
    protected function getDefaultHeaderActions(): array
    {
        return [
            EditAction::make('update_status')
                ->label(__('lunarpanel.blog::category.actions.edit_status.label'))
                ->modalHeading(__('lunarpanel.blog::category.actions.edit_status.heading'))
                ->record($this->record)
                ->form([
                    Radio::make('status')
                        ->options([
                            'published' => __('lunarpanel.blog::category.form.status.options.published.label'),
                            'draft' => __('lunarpanel.blog::category.form.status.options.draft.label'),
                        ])
                        ->descriptions([
                            'published' => __('lunarpanel.blog::category.form.status.options.published.description'),
                            'draft' => __('lunarpanel.blog::category.form.status.options.draft.description'),
                        ])
                        ->live(),
                ]),
            DeleteAction::make(),
        ];
    }

    /**
     * Get the relation managers for the page.
     */
    public function getRelationManagers(): array
    {
        return [];
    }
}
