<?php

namespace Lunar\Content\Filament\Resources;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Lunar\Admin\Support\Forms\Components\TranslatedText;
use Lunar\Admin\Support\Resources\BaseResource;
use Lunar\Content\Filament\Resources\MenuItemResource\Pages\CreateMenuItem;
use Lunar\Content\Filament\Resources\MenuItemResource\Pages\EditMenuItem;
use Lunar\Content\Filament\Resources\MenuItemResource\Pages\ListMenuItems;
use Lunar\Content\Models\ContentBlock;
use Lunar\Content\Support\MenuItemPages;
use Lunar\Models\Collection as CollectionModel;
use Lunar\Models\Contracts\Collection as CollectionContract;

class MenuItemResource extends BaseResource
{
    protected static ?string $model = ContentBlock::class;

    protected static ?string $slug = 'content/menu-items';

    /**
     * The permission required to access this resource.
     */
    protected static ?string $permission = 'content:manage';

    /**
     * Get the label for the resource.
     */
    public static function getLabel(): string
    {
        return __('lunarpanel.content::menu_item.label');
    }

    /**
     * Get the plural label for the resource.
     */
    public static function getPluralLabel(): string
    {
        return __('lunarpanel.content::menu_item.plural_label');
    }

    /**
     * Get the icon for the navigation.
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-bars-3-bottom-left';
    }

    /**
     * Get the navigation group.
     */
    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel.content::plugin.navigation.group');
    }

    /**
     * Scope the base Eloquent query to only menu item blocks.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', 'menu_item');
    }

    /**
     * Get the default form schema.
     */
    public static function getDefaultForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('lunarpanel.content::menu_item.sections.content'))
                    ->schema([
                        Select::make('data.link_type')
                            ->label(__('lunarpanel.content::menu_item.form.link_type.label'))
                            ->options([
                                'collection' => __('lunarpanel.content::menu_item.form.link_type.options.collection'),
                                'cms_page' => __('lunarpanel.content::menu_item.form.link_type.options.cms_page'),
                                'contact' => __('lunarpanel.content::menu_item.form.link_type.options.contact'),
                            ])
                            ->required()
                            ->live()
                            ->native(false),

                        Select::make('data.collection_id')
                            ->label(__('lunarpanel.content::menu_item.form.collection_id.label'))
                            ->searchable()
                            ->required(fn (Get $get): bool => $get('data.link_type') === 'collection')
                            ->visible(fn (Get $get): bool => $get('data.link_type') === 'collection')
                            ->getSearchResultsUsing(static function (string $search): array {
                                return get_search_builder(CollectionModel::class, $search)
                                    ->get()
                                    ->mapWithKeys(fn (CollectionContract $record): array => [
                                        $record->getKey() => $record->breadcrumb
                                            ->push($record->translateAttribute('name'))
                                            ->join(' > '),
                                    ])
                                    ->all();
                            })
                            ->getOptionLabelUsing(static function ($value): ?string {
                                $collection = CollectionModel::query()->find($value);

                                if (! $collection) {
                                    return null;
                                }

                                return $collection->breadcrumb
                                    ->push($collection->translateAttribute('name'))
                                    ->join(' > ');
                            }),

                        Select::make('data.cms_page')
                            ->label(__('lunarpanel.content::menu_item.form.cms_page.label'))
                            ->options(fn (): array => MenuItemPages::options())
                            ->required(fn (Get $get): bool => $get('data.link_type') === 'cms_page')
                            ->visible(fn (Get $get): bool => $get('data.link_type') === 'cms_page')
                            ->native(false),

                        TranslatedText::make('data.label')
                            ->label(__('lunarpanel.content::menu_item.form.label.label'))
                            ->helperText(__('lunarpanel.content::menu_item.form.label.helper'))
                            ->maxLength(100),
                    ]),

                Section::make(__('lunarpanel.content::menu_item.sections.visibility'))
                    ->schema([
                        Toggle::make('is_active')
                            ->label(__('lunarpanel.content::menu_item.form.is_active.label'))
                            ->default(true),
                    ]),
            ])
            ->columns(1);
    }

    /**
     * Get the default table schema.
     */
    public static function getDefaultTable(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->label(__('lunarpanel.content::menu_item.table.label.label'))
                    ->getStateUsing(fn (ContentBlock $record): ?string => static::displayLabel($record))
                    ->limit(60)
                    ->searchable(query: function ($query, string $search) {
                        $query->where('data->label', 'like', "%{$search}%");
                    }),

                TextColumn::make('link_type')
                    ->label(__('lunarpanel.content::menu_item.table.link_type.label'))
                    ->getStateUsing(fn (ContentBlock $record): ?string => match ($record->data['link_type'] ?? null) {
                        'collection' => __('lunarpanel.content::menu_item.form.link_type.options.collection'),
                        'cms_page' => __('lunarpanel.content::menu_item.form.link_type.options.cms_page'),
                        'contact' => __('lunarpanel.content::menu_item.form.link_type.options.contact'),
                        default => $record->data['link_type'] ?? null,
                    }),

                TextColumn::make('target')
                    ->label(__('lunarpanel.content::menu_item.table.target.label'))
                    ->getStateUsing(fn (ContentBlock $record): ?string => static::displayTarget($record))
                    ->limit(60),

                IconColumn::make('is_active')
                    ->label(__('lunarpanel.content::menu_item.table.is_active.label'))
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->paginated(false)
            ->reorderable('sort_order')
            ->reorderRecordsTriggerAction(
                fn (Action $action, bool $isReordering): Action => $action->button()
            )
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
     * Get the pages for the resource.
     */
    public static function getPages(): array
    {
        return [
            'index' => ListMenuItems::route('/'),
            'create' => CreateMenuItem::route('/create'),
            'edit' => EditMenuItem::route('/{record}/edit'),
        ];
    }

    /**
     * Resolve a human-readable label for the table.
     */
    protected static function displayLabel(ContentBlock $record): ?string
    {
        $label = $record->translateData('label');

        if (filled($label)) {
            return $label;
        }

        return static::displayTarget($record);
    }

    /**
     * Resolve a human-readable target for the table.
     */
    protected static function displayTarget(ContentBlock $record): ?string
    {
        $linkType = $record->data['link_type'] ?? null;

        return match ($linkType) {
            'collection' => static::collectionLabel($record->data['collection_id'] ?? null),
            'cms_page' => MenuItemPages::options()[$record->data['cms_page'] ?? ''] ?? ($record->data['cms_page'] ?? null),
            'contact' => __('lunarpanel.content::menu_item.form.link_type.options.contact'),
            default => null,
        };
    }

    protected static function collectionLabel(mixed $collectionId): ?string
    {
        if (! $collectionId) {
            return null;
        }

        $collection = CollectionModel::query()->find($collectionId);

        if (! $collection) {
            return null;
        }

        return $collection->breadcrumb
            ->push($collection->translateAttribute('name'))
            ->join(' > ');
    }
}
