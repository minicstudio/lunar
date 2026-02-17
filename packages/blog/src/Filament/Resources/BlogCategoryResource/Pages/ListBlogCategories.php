<?php

namespace Lunar\Blog\Filament\Resources\BlogCategoryResource\Pages;

use Filament\Actions\CreateAction;
use Illuminate\Database\Eloquent\Model;
use Lunar\Admin\Support\Pages\BaseListRecords;
use Lunar\Blog\Filament\Resources\BlogCategoryResource;
use Lunar\Models\Attribute;

class ListBlogCategories extends BaseListRecords
{
    /**
     * The resource class for the blog category.
     */
    protected static string $resource = BlogCategoryResource::class;

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
                    fn (Model $record): string => BlogCategoryResource::getUrl('edit', [
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
            BlogCategoryResource::getBaseNameFormComponent(),
        ];
    }

    /**
     * Create a new blog category record.
     */
    public static function createRecord(array $data, string $model): Model
    {
        $nameAttribute = Attribute::whereAttributeType($model::morphName())
            ->whereHandle('name')
            ->first()
            ->type;

        $category = $model::create([
            'status' => 'draft',
            'attribute_data' => [
                'name' => new $nameAttribute($data['name']),
            ],
        ]);

        return $category;
    }
}
