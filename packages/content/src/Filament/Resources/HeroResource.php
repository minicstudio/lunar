<?php

namespace Lunar\Content\Filament\Resources;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Lunar\Admin\Support\Forms\Components\TranslatedText;
use Lunar\Admin\Support\Resources\BaseResource;
use Lunar\Content\Filament\Resources\HeroResource\Pages\CreateHero;
use Lunar\Content\Filament\Resources\HeroResource\Pages\EditHero;
use Lunar\Content\Filament\Resources\HeroResource\Pages\ListHeroes;
use Lunar\Content\Models\ContentBlock;
use Lunar\Models\Collection as CollectionModel;
use Lunar\Models\Contracts\Collection as CollectionContract;

class HeroResource extends BaseResource
{
    protected static ?string $model = ContentBlock::class;

    protected static ?string $slug = 'content/heroes';

    /**
     * Determine if the current user has permission to access this resource.
     */
    protected static function hasPermission(): bool
    {
        return true;
    }

    /**
     * Get the label for the resource.
     */
    public static function getLabel(): string
    {
        return __('lunarpanel.content::hero.label');
    }

    /**
     * Get the plural label for the resource.
     */
    public static function getPluralLabel(): string
    {
        return __('lunarpanel.content::hero.plural_label');
    }

    /**
     * Get the icon for the navigation.
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-photo';
    }

    /**
     * Get the navigation group.
     */
    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel.content::plugin.navigation.group');
    }

    /**
     * Scope the base Eloquent query to only hero blocks.
     */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('type', 'hero');
    }

    /**
     * Get the default form schema.
     */
    public static function getDefaultForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('lunarpanel.content::hero.sections.content'))
                    ->schema([
                        TranslatedText::make('data.heading')
                            ->label(__('lunarpanel.content::hero.form.heading.label'))
                            ->required()
                            ->maxLength(255),

                        TranslatedText::make('data.subheading')
                            ->label(__('lunarpanel.content::hero.form.subheading.label'))
                            ->maxLength(500),

                        Grid::make(2)
                            ->schema([
                                TranslatedText::make('data.cta_label')
                                    ->label(__('lunarpanel.content::hero.form.cta_label.label'))
                                    ->maxLength(100),

                                Select::make('data.collection_id')
                                    ->label(__('lunarpanel.content::hero.form.collection_id.label'))
                                    ->searchable()
                                    ->nullable()
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
                            ]),

                        TextInput::make('data.cta_url')
                            ->label(__('lunarpanel.content::hero.form.cta_url.label'))
                            ->helperText(__('lunarpanel.content::hero.form.cta_url.helper'))
                            ->url()
                            ->maxLength(2048),
                    ]),

                Section::make(__('lunarpanel.content::hero.sections.media'))
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('hero_image')
                            ->label(__('lunarpanel.content::hero.form.image.label'))
                            ->collection('heroes')
                            ->image()
                            ->imageEditor()
                            ->maxFiles(1)
                            ->live(),

                        TranslatedText::make('data.image_alt')
                            ->label(__('lunarpanel.content::hero.form.image_alt.label'))
                            ->helperText(__('lunarpanel.content::hero.form.image_alt.helper'))
                            ->required(fn (Get $get, ?ContentBlock $record): bool => static::heroHasImage($get, $record))
                            ->maxLength(255),
                    ]),

                Section::make(__('lunarpanel.content::hero.sections.scheduling'))
                    ->schema([
                        Toggle::make('is_active')
                            ->label(__('lunarpanel.content::hero.form.is_active.label'))
                            ->default(true),

                        TextInput::make('sort_order')
                            ->label(__('lunarpanel.content::hero.form.sort_order.label'))
                            ->numeric()
                            ->default(0),

                        Grid::make(2)
                            ->schema([
                                DateTimePicker::make('starts_at')
                                    ->label(__('lunarpanel.content::hero.form.starts_at.label'))
                                    ->nullable(),

                                DateTimePicker::make('ends_at')
                                    ->label(__('lunarpanel.content::hero.form.ends_at.label'))
                                    ->nullable()
                                    ->after('starts_at'),
                            ]),
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
                ImageColumn::make('hero_image')
                    ->label(__('lunarpanel.content::hero.table.image.label'))
                    ->getStateUsing(fn (ContentBlock $record): ?string => $record->heroImageUrl('small')),

                TextColumn::make('heading')
                    ->label(__('lunarpanel.content::hero.table.heading.label'))
                    ->getStateUsing(fn (ContentBlock $record): ?string => $record->translateData('heading'))
                    ->limit(60)
                    ->searchable(query: function ($query, string $search) {
                        $query->where('data->heading', 'like', "%{$search}%");
                    }),

                IconColumn::make('is_active')
                    ->label(__('lunarpanel.content::hero.table.is_active.label'))
                    ->boolean(),

                TextColumn::make('sort_order')
                    ->label(__('lunarpanel.content::hero.table.sort_order.label'))
                    ->sortable(),

                TextColumn::make('starts_at')
                    ->label(__('lunarpanel.content::hero.table.starts_at.label'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('ends_at')
                    ->label(__('lunarpanel.content::hero.table.ends_at.label'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
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
            'index' => ListHeroes::route('/'),
            'create' => CreateHero::route('/create'),
            'edit' => EditHero::route('/{record}/edit'),
        ];
    }

    /**
     * Whether the form currently has a hero image (new upload or existing media).
     */
    protected static function heroHasImage(Get $get, ?ContentBlock $record): bool
    {
        $heroImage = $get('hero_image');

        if ($heroImage !== null) {
            if (is_array($heroImage)) {
                return count(array_filter($heroImage)) > 0;
            }

            return filled($heroImage);
        }

        return $record?->getFirstMedia('heroes') !== null;
    }
}
