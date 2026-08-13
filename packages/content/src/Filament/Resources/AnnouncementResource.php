<?php

namespace Lunar\Content\Filament\Resources;

use Filament\Forms\Components\ColorPicker;
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
use Lunar\Content\Filament\Resources\AnnouncementResource\Pages\CreateAnnouncement;
use Lunar\Content\Filament\Resources\AnnouncementResource\Pages\EditAnnouncement;
use Lunar\Content\Filament\Resources\AnnouncementResource\Pages\ListAnnouncements;
use Lunar\Content\Models\ContentBlock;
use Lunar\Content\Support\StorefrontColors;

class AnnouncementResource extends BaseResource
{
    protected static ?string $model = ContentBlock::class;

    protected static ?string $slug = 'content/announcements';

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
        return __('lunarpanel.content::announcement.label');
    }

    /**
     * Get the plural label for the resource.
     */
    public static function getPluralLabel(): string
    {
        return __('lunarpanel.content::announcement.plural_label');
    }

    /**
     * Get the icon for the navigation.
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-megaphone';
    }

    /**
     * Get the navigation group.
     */
    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel.content::plugin.navigation.group');
    }

    /**
     * Scope the base Eloquent query to only announcement blocks.
     */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('type', 'announcement');
    }

    /**
     * Get the default form schema.
     */
    public static function getDefaultForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('lunarpanel.content::announcement.sections.content'))
                    ->schema([
                        TranslatedText::make('data.text')
                            ->label(__('lunarpanel.content::announcement.form.text.label'))
                            ->helperText(__('lunarpanel.content::announcement.form.text.helper'))
                            ->optionRichtext(true)
                            ->richtextToolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'undo',
                                'redo',
                            ])
                            ->required(),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('data.link_url')
                                    ->label(__('lunarpanel.content::announcement.form.link_url.label'))
                                    ->url()
                                    ->maxLength(2048),

                                TranslatedText::make('data.link_label')
                                    ->label(__('lunarpanel.content::announcement.form.link_label.label'))
                                    ->maxLength(100),
                            ]),

                        Grid::make(2)
                            ->schema([
                                ColorPicker::make('data.bg_color')
                                    ->label(__('lunarpanel.content::announcement.form.bg_color.label'))
                                    ->default(fn (): string => StorefrontColors::primary())
                                    ->afterStateHydrated(function (ColorPicker $component, ?string $state): void {
                                        if (blank($state)) {
                                            $component->state(StorefrontColors::primary());
                                        }
                                    }),

                                ColorPicker::make('data.text_color')
                                    ->label(__('lunarpanel.content::announcement.form.text_color.label'))
                                    ->default(fn (): string => StorefrontColors::text())
                                    ->afterStateHydrated(function (ColorPicker $component, ?string $state): void {
                                        if (blank($state)) {
                                            $component->state(StorefrontColors::text());
                                        }
                                    }),
                            ]),

                        Toggle::make('is_closable')
                            ->label(__('lunarpanel.content::announcement.form.is_closable.label'))
                            ->helperText(__('lunarpanel.content::announcement.form.is_closable.helper'))
                            ->default(false),
                    ]),

                Section::make(__('lunarpanel.content::announcement.sections.scheduling'))
                    ->schema([
                        Toggle::make('is_active')
                            ->label(__('lunarpanel.content::announcement.form.is_active.label'))
                            ->default(true),

                        TextInput::make('sort_order')
                            ->label(__('lunarpanel.content::announcement.form.sort_order.label'))
                            ->numeric()
                            ->default(0),

                        Grid::make(2)
                            ->schema([
                                DateTimePicker::make('starts_at')
                                    ->label(__('lunarpanel.content::announcement.form.starts_at.label'))
                                    ->nullable(),

                                DateTimePicker::make('ends_at')
                                    ->label(__('lunarpanel.content::announcement.form.ends_at.label'))
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
                TextColumn::make('text')
                    ->label(__('lunarpanel.content::announcement.table.text.label'))
                    ->getStateUsing(function (ContentBlock $record): ?string {
                        $text = $record->translateData('text');

                        return filled($text) ? strip_tags($text) : null;
                    })
                    ->limit(60)
                    ->searchable(query: function ($query, string $search) {
                        $query->where('data->text', 'like', "%{$search}%");
                    }),

                IconColumn::make('is_active')
                    ->label(__('lunarpanel.content::announcement.table.is_active.label'))
                    ->boolean(),

                IconColumn::make('data.is_closable')
                    ->label(__('lunarpanel.content::announcement.table.is_closable.label'))
                    ->boolean(),

                TextColumn::make('sort_order')
                    ->label(__('lunarpanel.content::announcement.table.sort_order.label'))
                    ->sortable(),

                TextColumn::make('starts_at')
                    ->label(__('lunarpanel.content::announcement.table.starts_at.label'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('ends_at')
                    ->label(__('lunarpanel.content::announcement.table.ends_at.label'))
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
            'index' => ListAnnouncements::route('/'),
            'create' => CreateAnnouncement::route('/create'),
            'edit' => EditAnnouncement::route('/{record}/edit'),
        ];
    }
}
