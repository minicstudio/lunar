<?php

namespace Lunar\Content\Filament\Resources;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Lunar\Admin\Support\Forms\Components\TranslatedText;
use Lunar\Admin\Support\Resources\BaseResource;
use Lunar\Content\Filament\Resources\PopupResource\Pages\CreatePopup;
use Lunar\Content\Filament\Resources\PopupResource\Pages\EditPopup;
use Lunar\Content\Filament\Resources\PopupResource\Pages\ListPopups;
use Lunar\Content\Models\ContentBlock;
use Lunar\Content\Support\PopupDisplayPages;

class PopupResource extends BaseResource
{
    protected static ?string $model = ContentBlock::class;

    protected static ?string $slug = 'content/popups';

    /**
     * The permission required to access this resource.
     */
    protected static ?string $permission = 'content:manage';

    /**
     * Get the label for the resource.
     */
    public static function getLabel(): string
    {
        return __('lunarpanel.content::popup.label');
    }

    /**
     * Get the plural label for the resource.
     */
    public static function getPluralLabel(): string
    {
        return __('lunarpanel.content::popup.plural_label');
    }

    /**
     * Get the icon for the navigation.
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-window';
    }

    /**
     * Get the navigation group.
     */
    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel.content::plugin.navigation.group');
    }

    /**
     * Scope the base Eloquent query to only popup blocks.
     */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('type', 'popup');
    }

    /**
     * Get the default form schema.
     */
    public static function getDefaultForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('lunarpanel.content::popup.sections.content'))
                    ->schema([
                        TranslatedText::make('data.title')
                            ->label(__('lunarpanel.content::popup.form.title.label'))
                            ->required()
                            ->maxLength(255),

                        TranslatedText::make('data.body')
                            ->label(__('lunarpanel.content::popup.form.body.label'))
                            ->maxLength(2000),

                        TextInput::make('data.discount_code')
                            ->label(__('lunarpanel.content::popup.form.discount_code.label'))
                            ->helperText(__('lunarpanel.content::popup.form.discount_code.helper'))
                            ->maxLength(100),

                        Grid::make(2)
                            ->schema([
                                TranslatedText::make('data.cta_label')
                                    ->label(__('lunarpanel.content::popup.form.cta_label.label'))
                                    ->maxLength(100),

                                TextInput::make('data.cta_url')
                                    ->label(__('lunarpanel.content::popup.form.cta_url.label'))
                                    ->url()
                                    ->maxLength(2048),
                            ]),
                    ]),

                Section::make(__('lunarpanel.content::popup.sections.timing'))
                    ->schema([
                        CheckboxList::make('data.display_on')
                            ->label(__('lunarpanel.content::popup.form.display_on.label'))
                            ->helperText(__('lunarpanel.content::popup.form.display_on.helper'))
                            ->options(PopupDisplayPages::options())
                            ->default(PopupDisplayPages::defaults())
                            ->columns(2)
                            ->bulkToggleable(),

                        TextInput::make('data.delay_seconds')
                            ->label(__('lunarpanel.content::popup.form.delay_seconds.label'))
                            ->helperText(__('lunarpanel.content::popup.form.delay_seconds.helper'))
                            ->numeric()
                            ->minValue(0)
                            ->default(5)
                            ->required(),

                        Toggle::make('show_once')
                            ->label(__('lunarpanel.content::popup.form.show_once.label'))
                            ->helperText(__('lunarpanel.content::popup.form.show_once.helper'))
                            ->default(true),

                        Toggle::make('is_active')
                            ->label(__('lunarpanel.content::popup.form.is_active.label'))
                            ->default(true),

                        TextInput::make('sort_order')
                            ->label(__('lunarpanel.content::popup.form.sort_order.label'))
                            ->numeric()
                            ->default(0),

                        Grid::make(2)
                            ->schema([
                                DateTimePicker::make('starts_at')
                                    ->label(__('lunarpanel.content::popup.form.starts_at.label'))
                                    ->helperText(__('lunarpanel.content::popup.form.starts_at.helper'))
                                    ->nullable(),

                                DateTimePicker::make('ends_at')
                                    ->label(__('lunarpanel.content::popup.form.ends_at.label'))
                                    ->helperText(__('lunarpanel.content::popup.form.ends_at.helper'))
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
                TextColumn::make('title')
                    ->label(__('lunarpanel.content::popup.table.title.label'))
                    ->getStateUsing(fn (ContentBlock $record): ?string => $record->translateData('title'))
                    ->limit(60)
                    ->searchable(query: function ($query, string $search) {
                        $query->where('data->title', 'like', "%{$search}%");
                    }),

                TextColumn::make('data.delay_seconds')
                    ->label(__('lunarpanel.content::popup.table.delay_seconds.label'))
                    ->suffix('s'),

                TextColumn::make('data.display_on')
                    ->label(__('lunarpanel.content::popup.table.display_on.label'))
                    ->getStateUsing(function (ContentBlock $record): string {
                        $displayOn = $record->data['display_on'] ?? [];

                        if (! is_array($displayOn) || $displayOn === []) {
                            return __('lunarpanel.content::popup.table.display_on.none');
                        }

                        $options = PopupDisplayPages::options();

                        return collect($displayOn)
                            ->map(fn (string $page) => $options[$page] ?? $page)
                            ->implode(', ');
                    }),

                IconColumn::make('is_active')
                    ->label(__('lunarpanel.content::popup.table.is_active.label'))
                    ->boolean(),

                IconColumn::make('data.show_once')
                    ->label(__('lunarpanel.content::popup.table.show_once.label'))
                    ->boolean(),

                TextColumn::make('starts_at')
                    ->label(__('lunarpanel.content::popup.table.starts_at.label'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('ends_at')
                    ->label(__('lunarpanel.content::popup.table.ends_at.label'))
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
            'index' => ListPopups::route('/'),
            'create' => CreatePopup::route('/create'),
            'edit' => EditPopup::route('/{record}/edit'),
        ];
    }
}
