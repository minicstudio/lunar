<?php

namespace Lunar\Admin\Filament\Resources\ProductOptionResource\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Lunar\Admin\Events\ProductOptionValueUpdated;
use Lunar\Admin\Support\Forms\Components\TranslatedText;
use Lunar\Admin\Support\RelationManagers\BaseRelationManager;
use Lunar\Admin\Support\Tables\Columns\TranslatedTextColumn;

class ValuesRelationManager extends BaseRelationManager
{
    protected static string $relationship = 'values';

    public function getTableRecordTitle(Model $record): ?string
    {
        return $record->translate('name');
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('lunarpanel::productoption.values.title');
    }

    /**
     * Check if the option is a color option
     */
    protected function isColorOption(): bool
    {

        return Str::of($this->getOwnerRecord()->handle ?? '')
            ->lower()
            ->is(['color', 'coloare', 'szín']);
    }

    /**
     * Get the URL of the multicolor image
     */
    protected function multicolorImageUrl(): string
    {
        return asset('vendor/lunarpanel/multicolor.webp');
    }

    /**
     * Get the HTML of the multicolor image
     */
    protected function multicolorImageHtml(): HtmlString
    {
        $url = $this->multicolorImageUrl();

        return new HtmlString(
            "<img src=\"{$url}\" style=\"width: 24px; height: 24px; min-width: 24px; border-radius: 50%; object-fit: cover;\" />"
        );
    }

    /**
     * Get the HTML of the multicolor image with a border
     */
    protected function multicolorImageFullHtml(): HtmlString
    {
        $url = $this->multicolorImageUrl();

        return new HtmlString(
            "<img src=\"{$url}\" style=\"max-width: 100%; border-radius: 8px; border: 1px solid rgba(0,0,0,0.15);\" />"
        );
    }

    public function getDefaultForm(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(2)
                    ->schema([
                        TranslatedText::make('name')
                            ->label(__('lunarpanel::productoption.values.form.name.label'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(fn () => $this->isColorOption() ? 1 : 'full'),
                        Group::make()
                            ->schema([
                                Toggle::make('meta.multicolor')
                                    ->label(__('lunarpanel::productoption.values.form.multicolor.label'))
                                    ->live(),
                                Placeholder::make('multicolor_preview')
                                    ->hiddenLabel()
                                    ->content(fn () => $this->multicolorImageFullHtml())
                                    ->visible(fn (Get $get) => (bool) $get('meta.multicolor')),
                                ColorPicker::make('meta.color')
                                    ->label(__('lunarpanel::productoption.values.form.swatch.label'))
                                    ->visible(fn (Get $get) => ! $get('meta.multicolor')),
                            ])
                            ->visible(fn () => $this->isColorOption()),
                    ]),
            ]);
    }

    public function getDefaultTable(Table $table): Table
    {
        return $table
            ->columns([
                TranslatedTextColumn::make('name')
                    ->label(__('lunarpanel::productoption.values.table.name.label')),
                TextColumn::make('swatch')
                    ->label(__('lunarpanel::productoption.values.table.swatch.label'))
                    ->html()
                    ->getStateUsing(function ($record) {
                        $meta = $record->meta ? $record->meta->getArrayCopy() : [];

                        if ($meta['multicolor'] ?? false) {
                            return $this->multicolorImageHtml()->toHtml();
                        }

                        $color = $meta['color'] ?? null;

                        if (! $color) {
                            return '';
                        }

                        return '<div style="background-color: '.e($color).'; width: 24px; height: 24px; min-width: 24px; border-radius: 50%; border: 1px solid rgba(0,0,0,0.15);"></div>';
                    })
                    ->visible(fn () => $this->isColorOption()),
                Tables\Columns\TextColumn::make('position')
                    ->label(__('lunarpanel::productoption.values.table.position.label')),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('lunarpanel::productoption.values.table.actions.create.label'))
                    ->modalHeading(__('lunarpanel::productoption.values.table.actions.create.heading')),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalHeading(__('lunarpanel::productoption.values.table.actions.edit.heading'))
                    ->after(function (Model $record) {
                        ProductOptionValueUpdated::dispatch($record);
                    }),
                DeleteAction::make()
                    ->modalHeading(__('lunarpanel::productoption.values.table.actions.delete.heading')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalHeading(__('lunarpanel::productoption.values.table.actions.delete.bulk.heading')),
                ]),
            ])
            ->defaultSort('position', 'asc')
            ->reorderable('position');
    }
}
