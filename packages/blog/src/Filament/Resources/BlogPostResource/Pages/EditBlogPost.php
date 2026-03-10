<?php

namespace Lunar\Blog\Filament\Resources\BlogPostResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Support\Facades\FilamentIcon;
use Illuminate\Contracts\Support\Htmlable;
use Lunar\Admin\Support\Pages\BaseEditRecord;
use Lunar\Blog\Filament\Resources\BlogPostResource;
use Lunar\Models\Language;

class EditBlogPost extends BaseEditRecord
{
    /**
     * The resource class for the blog post.
     */
    protected static string $resource = BlogPostResource::class;

    /**
     * Get the title for the page.
     *
     * @return string
     */
    public function getTitle(): string|Htmlable
    {
        return __('lunarpanel.blog::post.edit.label');
    }

    /**
     * Get the navigation label for the page.
     */
    public static function getNavigationLabel(): string
    {
        return __('lunarpanel.blog::post.edit.label');
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
        $defaultLanguage = Language::whereDefault(1)->first();
        $slug = $this->record->urls()
            ->whereDefault(1)->whereLanguageId($defaultLanguage->id)->value('slug');

        return [
            EditAction::make('update_status')
                ->label(__('lunarpanel.blog::post.actions.edit_status.label'))
                ->modalHeading(__('lunarpanel.blog::post.actions.edit_status.heading'))
                ->record($this->record)
                ->form([
                    Radio::make('status')
                        ->options([
                            'published' => __('lunarpanel.blog::post.form.status.options.published.label'),
                            'draft' => __('lunarpanel.blog::post.form.status.options.draft.label'),
                        ])
                        ->descriptions([
                            'published' => __('lunarpanel.blog::post.form.status.options.published.description'),
                            'draft' => __('lunarpanel.blog::post.form.status.options.draft.description'),
                        ])
                        ->live(),
                ]),
            DeleteAction::make(),
            Action::make('preview')
                ->label(__('lunarpanel.blog::post.actions.preview.label'))
                ->color('primary')
                ->icon('heroicon-o-eye')
                ->url(url('blog', ['blogSlug' => $slug]))
                ->openUrlInNewTab(true),
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
