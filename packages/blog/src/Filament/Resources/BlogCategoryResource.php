<?php

namespace Lunar\Blog\Filament\Resources;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\SubNavigationPosition;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Lunar\Admin\Support\Forms\Components\Attributes;
use Lunar\Admin\Support\Forms\Components\TranslatedText as TranslatedTextInput;
use Lunar\Admin\Support\Resources\BaseResource;
use Lunar\Admin\Support\Tables\Columns\TranslatedTextColumn;
use Lunar\Blog\Filament\Resources\BlogCategoryResource\Pages\EditBlogCategory;
use Lunar\Blog\Filament\Resources\BlogCategoryResource\Pages\ListBlogCategories;
use Lunar\Blog\Filament\Resources\BlogCategoryResource\Pages\ManageBlogCategoryAvailability;
use Lunar\Blog\Filament\Resources\BlogCategoryResource\Pages\ManageBlogCategoryUrls;
use Lunar\Blog\Models\BlogCategory;
use Lunar\FieldTypes\Text;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Attribute;

class BlogCategoryResource extends BaseResource
{
    /**
     * The model associated with the blog category resource.
     */
    protected static ?string $model = BlogCategory::class;

    /**
     * The position of the sub-navigation.
     */
    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::End;

    /**
     * Determine if the current user has permission to access this resource.
     */
    protected static function hasPermission(): bool
    {
        // TODO: https://minicstudio.atlassian.net/jira/software/projects/LFP/boards/95/backlog?selectedIssue=LFP-347
        if (! config('lunar.blog.enabled')) {
            return false;
        }

        return true;
    }

    /**
     * Get the label for the resource.
     */
    public static function getLabel(): string
    {
        return __('lunarpanel.blog::category.label');
    }

    /**
     * Get the plural label for the resource.
     */
    public static function getPluralLabel(): string
    {
        return __('lunarpanel.blog::category.plural_label');
    }

    /**
     * Get the icon for the resource in the navigation.
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-rectangle-stack';
    }

    /**
     * Get the navigation group for the resource.
     */
    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel.blog::navigation.sections.blog');
    }

    /**
     * Get the default form schema for the resource.
     */
    public static function getDefaultForm(Form $form): Form
    {
        return $form
            ->schema([
                static::getAttributeDataFormComponent(),
            ])
            ->columns(1);
    }

    /**
     * Get the form component for the base name attribute.
     */
    public static function getBaseNameFormComponent(): Component
    {
        $nameType = Attribute::whereHandle('name')
            ->whereAttributeType(static::getModel()::morphName())
            ->first()?->type ?: TranslatedText::class;

        $component = TranslatedTextInput::make('name');

        if ($nameType == Text::class) {
            $component = TextInput::make('name');
        }

        return $component->label(__('lunarpanel.blog::category.form.name.label'))->required();
    }

    /**
     * Get the form component for the attribute data.
     */
    protected static function getAttributeDataFormComponent(): Component
    {
        return Attributes::make();
    }

    /**
     * Get the default table schema for the resource.
     */
    public static function getDefaultTable(Table $table): Table
    {
        return $table
            ->columns(static::getTableColumns())
            ->filters([
                static::getStatusFilter(),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Get the filter for the status field.
     */
    protected static function getStatusFilter(): SelectFilter
    {
        return SelectFilter::make('status')
            ->label(__('lunarpanel.blog::post.filters.status.label'))
            ->options([
                'draft' => __('lunarpanel.blog::post.filters.status.options.draft'),
                'published' => __('lunarpanel.blog::post.filters.status.options.published'),
            ])
            ->placeholder(__('lunarpanel.blog::post.filters.status.placeholder'));
    }

    /**
     * Get the columns for the table.
     */
    public static function getTableColumns(): array
    {
        return [
            static::getNameTableColumn(),
            static::getStatusTableColumn(),
        ];
    }

    /**
     * Get the column for the name attribute in the table.
     */
    public static function getNameTableColumn(): Column
    {
        return TranslatedTextColumn::make('attribute_data.name')
            ->attributeData()
            ->limitedTooltip()
            ->limit(50)
            ->label(__('lunarpanel.blog::category.table.name.label'))
            ->searchable();
    }

    /**
     * Get the column for the status in the table.
     */
    public static function getStatusTableColumn(): Column
    {
        return TextColumn::make('status')
            ->label(__('lunarpanel.blog::category.table.status.label'))
            ->badge()
            ->getStateUsing(fn(Model $record) => $record->status)
            ->formatStateUsing(fn($state) => __('lunarpanel.blog::category.table.status.states.' . $state))
            ->color(
                fn(string $state): string => match ($state) {
                    'draft' => 'warning',
                    'published' => 'success',
                }
            );
    }

    /**
     * Get the default sub-navigation pages for the resource.
     */
    public static function getDefaultSubNavigation(): array
    {
        return [
            EditBlogCategory::class,
            ManageBlogCategoryAvailability::class,
            ManageBlogCategoryUrls::class,
        ];
    }

    /**
     * Get the pages for the resource.
     */
    public static function getPages(): array
    {
        return [
            'index' => ListBlogCategories::route('/'),
            'edit' => EditBlogCategory::route('/{record}/edit'),
            'availability' => ManageBlogCategoryAvailability::route('/{record}/availability'),
            'urls' => ManageBlogCategoryUrls::route('/{record}/urls'),
        ];
    }
}
