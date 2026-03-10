<?php

namespace Lunar\Blog\Filament\Resources\BlogPostResource\Pages;

use Filament\Actions\CreateAction;
use Illuminate\Database\Eloquent\Model;
use Lunar\Admin\Support\Pages\BaseListRecords;
use Lunar\Blog\Filament\Resources\BlogPostResource;
use Lunar\Models\Attribute;

class ListBlogPosts extends BaseListRecords
{
    /**
     * The resource class for the blog post.
     */
    protected static string $resource = BlogPostResource::class;

    /**
     * Get the default header actions for the page.
     */
    protected function getDefaultHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->createAnother(false)
                ->form(static::createActionFormInputs())
                ->using(fn (array $data, string $model) => static::createRecord($data, $model))
                ->successRedirectUrl(
                    fn (Model $record): string => BlogPostResource::getUrl('edit', [
                        'record' => $record,
                    ])
                ),
        ];
    }

    /**
     * Get the form inputs for the create action.
     */
    public static function createActionFormInputs(): array
    {
        return [
            BlogPostResource::getBaseTitleFormComponent(),
        ];
    }

    /**
     * Create a new blog post record.
     */
    public static function createRecord(array $data, string $model): Model
    {
        $nameAttribute = Attribute::whereAttributeType($model::morphName())
            ->whereHandle('title')
            ->first()
            ->type;

        $category = $model::create([
            'status' => 'draft',
            'author_id' => auth()->guard()->user()->id,
            'attribute_data' => [
                'title' => new $nameAttribute($data['title']),
            ],
        ]);

        return $category;
    }
}
