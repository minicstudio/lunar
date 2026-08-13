<?php

namespace Lunar\Content\Filament\Resources;

use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Lunar\Admin\Support\Forms\Components\TranslatedText;
use Lunar\Admin\Support\Resources\BaseResource;
use Lunar\Content\Filament\Resources\ContactInfoResource\Pages\CreateContactInfo;
use Lunar\Content\Filament\Resources\ContactInfoResource\Pages\EditContactInfo;
use Lunar\Content\Filament\Resources\ContactInfoResource\Pages\ListContactInfos;
use Lunar\Content\Models\ContentBlock;

class ContactInfoResource extends BaseResource
{
    protected static ?string $model = ContentBlock::class;

    protected static ?string $slug = 'content/contact-details';

    /**
     * The permission required to access this resource.
     */
    protected static ?string $permission = 'content:manage';

    /**
     * Get the label for the resource.
     */
    public static function getLabel(): string
    {
        return __('lunarpanel.content::contact_info.label');
    }

    /**
     * Get the plural label for the resource.
     */
    public static function getPluralLabel(): string
    {
        return __('lunarpanel.content::contact_info.plural_label');
    }

    /**
     * Get the icon for the navigation.
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-map-pin';
    }

    /**
     * Get the navigation group.
     */
    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel.content::plugin.navigation.group');
    }

    /**
     * Jump straight to create or edit — contact details are a singleton.
     */
    public static function getNavigationUrl(): string
    {
        $record = static::getEloquentQuery()->first();

        return $record
            ? static::getUrl('edit', ['record' => $record])
            : static::getUrl('create');
    }

    /**
     * Only allow creating when no contact_info block exists yet.
     */
    public static function canCreate(): bool
    {
        if (! static::hasPermission()) {
            return false;
        }

        return ! static::getEloquentQuery()->exists();
    }

    /**
     * Scope the base Eloquent query to only contact info blocks.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', 'contact_info');
    }

    /**
     * Get the default form schema.
     */
    public static function getDefaultForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('lunarpanel.content::contact_info.sections.content'))
                    ->schema([
                        TranslatedText::make('data.intro')
                            ->label(__('lunarpanel.content::contact_info.form.intro.label'))
                            ->helperText(__('lunarpanel.content::contact_info.form.intro.helper'))
                            ->columnSpanFull(),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('data.phone')
                                    ->label(__('lunarpanel.content::contact_info.form.phone.label'))
                                    ->tel()
                                    ->maxLength(50),

                                TextInput::make('data.email')
                                    ->label(__('lunarpanel.content::contact_info.form.email.label'))
                                    ->email()
                                    ->maxLength(255),
                            ]),
                    ]),

                Section::make(__('lunarpanel.content::contact_info.sections.address'))
                    ->schema([
                        TranslatedText::make('data.address.street')
                            ->label(__('lunarpanel.content::contact_info.form.street.label'))
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Grid::make(2)
                            ->schema([
                                TranslatedText::make('data.address.city')
                                    ->label(__('lunarpanel.content::contact_info.form.city.label'))
                                    ->maxLength(100),

                                TextInput::make('data.address.postal_code')
                                    ->label(__('lunarpanel.content::contact_info.form.postal_code.label'))
                                    ->maxLength(20),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TranslatedText::make('data.address.country')
                                    ->label(__('lunarpanel.content::contact_info.form.country.label'))
                                    ->maxLength(100),

                                TextInput::make('data.address.country_code')
                                    ->label(__('lunarpanel.content::contact_info.form.country_code.label'))
                                    ->helperText(__('lunarpanel.content::contact_info.form.country_code.helper'))
                                    ->maxLength(2),
                            ]),
                    ]),

                Section::make(__('lunarpanel.content::contact_info.sections.visibility'))
                    ->schema([
                        Toggle::make('is_active')
                            ->label(__('lunarpanel.content::contact_info.form.is_active.label'))
                            ->helperText(__('lunarpanel.content::contact_info.form.is_active.helper'))
                            ->default(true),
                    ]),
            ])
            ->columns(1);
    }

    /**
     * Get the default table schema (used if the list page is opened directly).
     */
    public static function getDefaultTable(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')
                    ->label(__('lunarpanel.content::contact_info.table.email.label'))
                    ->getStateUsing(fn (ContentBlock $record): ?string => Arr::get($record->data, 'email')),

                TextColumn::make('phone')
                    ->label(__('lunarpanel.content::contact_info.table.phone.label'))
                    ->getStateUsing(fn (ContentBlock $record): ?string => Arr::get($record->data, 'phone')),

                IconColumn::make('is_active')
                    ->label(__('lunarpanel.content::contact_info.table.is_active.label'))
                    ->boolean(),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->paginated(false);
    }

    /**
     * Get the pages for the resource.
     */
    public static function getPages(): array
    {
        return [
            'index' => ListContactInfos::route('/'),
            'create' => CreateContactInfo::route('/create'),
            'edit' => EditContactInfo::route('/{record}/edit'),
        ];
    }
}
