<?php

namespace Lunar\Blog\Filament\Resources;

use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\SubNavigationPosition;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Lunar\Admin\Support\Forms\Components\Attributes;
use Lunar\Admin\Support\Forms\Components\TranslatedText as TranslatedTextInput;
use Lunar\Admin\Support\Resources\BaseResource;
use Lunar\Admin\Support\Tables\Columns\TranslatedTextColumn;
use Lunar\Blog\Filament\Resources\BlogCategoryResource\Pages\ListBlogCategories;
use Lunar\Blog\Filament\Resources\BlogPostResource\Pages\EditBlogPost;
use Lunar\Blog\Filament\Resources\BlogPostResource\Pages\ListBlogPosts;
use Lunar\Blog\Filament\Resources\BlogPostResource\Pages\ManageBlogPostAvailability;
use Lunar\Blog\Filament\Resources\BlogPostResource\Pages\ManageBlogPostUrls;
use Lunar\Blog\Models\BlogCategory;
use Lunar\Blog\Models\BlogPost;
use Lunar\FieldTypes\Text;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Attribute;
use Lunar\Models\Language;

class BlogPostResource extends BaseResource
{
    /**
     * The model associated with the blog post resource.
     */
    protected static ?string $model = BlogPost::class;

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
        return __('lunarpanel.blog::post.label');
    }

    /**
     * Get the plural label for the resource.
     */
    public static function getPluralLabel(): string
    {
        return __('lunarpanel.blog::post.plural_label');
    }

    /**
     * Get the icon for the resource in the navigation.
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-book-open';
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
                static::getBottomFormComponent(),
            ])
            ->columns(1);
    }

    /**
     * Get the form component for the base title attribute.
     */
    public static function getBaseTitleFormComponent(): Component
    {
        $nameType = Attribute::whereHandle('title')
            ->whereAttributeType(static::getModel()::morphName())
            ->first()?->type ?: TranslatedText::class;

        $component = TranslatedTextInput::make('title');

        if ($nameType == Text::class) {
            $component = TextInput::make('title');
        }

        return $component->label(__('lunarpanel.blog::post.form.title.label'))->required();
    }

    /**
     * Get the form component for the attribute data.
     */
    protected static function getAttributeDataFormComponent(): Component
    {
        return Attributes::make()
            ->afterStateHydrated(function (callable $set, callable $get) {
                $firstName = $get('attribute_data.author_first_name') ?? new Text;
                $lastName = $get('attribute_data.author_last_name') ?? new Text;

                $user = Auth::user();

                if ($firstName->getValue() === '') {
                    $firstName->setValue($user?->firstname ?? null);
                }

                if ($lastName->getValue() === '') {
                    $lastName->setValue($user?->lastname ?? null);
                }

                $set('attribute_data.author_first_name', $firstName);
                $set('attribute_data.author_last_name', $lastName);
            });
    }

    /**
     * Get the form component for the category relation.
     */
    protected static function getBottomFormComponent(): Component
    {
        return Section::make(__('lunarpanel.blog::post.section.categories.title'))
            ->description(__('lunarpanel.blog::post.section.categories.description'))
            ->id('categories')
            ->headerActions([
                Action::make('createCategory')
                    ->label(__('lunarpanel.blog::post.section.categories.action.label'))
                    ->button()
                    ->modal()
                    ->modalHeading(__('lunarpanel.blog::post.section.categories.action.modal.heading'))
                    ->modalSubmitActionLabel(__('lunarpanel.blog::post.section.categories.action.modal.submit'))
                    ->form(ListBlogCategories::createActionFormInputs())
                    ->action(function (array $data) {
                        ListBlogCategories::createRecord($data, BlogCategory::class);

                        Notification::make()
                            ->title(__('lunarpanel.blog::post.section.categories.action.notification.title'))
                            ->body(__('lunarpanel.blog::post.section.categories.action.notification.body'))
                            ->success()
                            ->send();
                    }),
            ])
            ->schema([
                Select::make('blogCategories')
                    ->label(__('lunarpanel.blog::post.form.categories.title.label'))
                    ->relationship(
                        name: 'blogCategories',
                    )
                    // TODO: Replace default with dynamic language support when multi-language display is implemented.
                    ->getOptionLabelFromRecordUsing(function ($record) {
                        $defaultLanguage = Language::where('default', true)->first()?->code ?? 'en';

                        return $record->attribute_data['name']->getValue()[$defaultLanguage]->getValue() ?? 'N/A';
                    }),
            ]);
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
            static::getTitleTableColumn(),
            static::getStatusTableColumn(),
            static::getAuthorTableColumn(),
        ];
    }

    /**
     * Get the column for the title attribute in the table.
     */
    public static function getTitleTableColumn(): Column
    {
        return TranslatedTextColumn::make('attribute_data.title')
            ->attributeData()
            ->limitedTooltip()
            ->limit(50)
            ->label(__('lunarpanel.blog::post.table.title.label'))
            ->searchable();
    }

    /**
     * Get the column for the status in the table.
     */
    public static function getStatusTableColumn(): Column
    {
        return TextColumn::make('status')
            ->label(__('lunarpanel.blog::post.table.status.label'))
            ->badge()
            ->getStateUsing(fn(Model $record) => $record->status)
            ->formatStateUsing(fn($state) => __('lunarpanel.blog::post.table.status.states.' . $state))
            ->color(
                fn(string $state): string => match ($state) {
                    'draft' => 'warning',
                    'published' => 'success',
                }
            );
    }

    /**
     * Get the column for the author field in the table.
     */
    protected static function getAuthorTableColumn(): Column
    {
        return TextColumn::make('author.full_name')
            ->label(__('lunarpanel.blog::post.table.author.label'))
            ->getStateUsing(fn(Model $record) => $record?->authorFullName);
    }

    /**
     * Get the default sub-navigation pages for the resource.
     */
    public static function getDefaultSubNavigation(): array
    {
        return [
            EditBlogPost::class,
            ManageBlogPostAvailability::class,
            ManageBlogPostUrls::class,
        ];
    }

    /**
     * Get the pages for the resource.
     */
    public static function getPages(): array
    {
        return [
            'index' => ListBlogPosts::route('/'),
            'edit' => EditBlogPost::route('/{record}/edit'),
            'availability' => ManageBlogPostAvailability::route('/{record}/availability'),
            'urls' => ManageBlogPostUrls::route('/{record}/urls'),
        ];
    }
}
