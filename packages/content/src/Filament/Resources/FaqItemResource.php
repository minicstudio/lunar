<?php

namespace Lunar\Content\Filament\Resources;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
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
use Lunar\Content\Filament\Resources\FaqItemResource\Pages\CreateFaqItem;
use Lunar\Content\Filament\Resources\FaqItemResource\Pages\EditFaqItem;
use Lunar\Content\Filament\Resources\FaqItemResource\Pages\ListFaqItems;
use Lunar\Content\Models\ContentBlock;

class FaqItemResource extends BaseResource
{
    protected static ?string $model = ContentBlock::class;

    protected static ?string $slug = 'content/faq-items';

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
        return __('lunarpanel.content::faq_item.label');
    }

    /**
     * Get the plural label for the resource.
     */
    public static function getPluralLabel(): string
    {
        return __('lunarpanel.content::faq_item.plural_label');
    }

    /**
     * Get the icon for the navigation.
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-question-mark-circle';
    }

    /**
     * Get the navigation group.
     */
    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel.content::plugin.navigation.group');
    }

    /**
     * Scope the base Eloquent query to only FAQ item blocks.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', 'faq_item');
    }

    /**
     * Get the default form schema.
     */
    public static function getDefaultForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('lunarpanel.content::faq_item.sections.content'))
                    ->schema([
                        TranslatedText::make('data.question')
                            ->label(__('lunarpanel.content::faq_item.form.question.label'))
                            ->required()
                            ->maxLength(500),

                        TranslatedText::make('data.answer')
                            ->label(__('lunarpanel.content::faq_item.form.answer.label'))
                            ->helperText(__('lunarpanel.content::faq_item.form.answer.helper'))
                            ->optionRichtext(true)
                            ->required(),
                    ]),

                Section::make(__('lunarpanel.content::faq_item.sections.visibility'))
                    ->schema([
                        Toggle::make('is_active')
                            ->label(__('lunarpanel.content::faq_item.form.is_active.label'))
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
                TextColumn::make('question')
                    ->label(__('lunarpanel.content::faq_item.table.question.label'))
                    ->getStateUsing(fn (ContentBlock $record): ?string => $record->translateData('question'))
                    ->limit(80)
                    ->searchable(query: function ($query, string $search) {
                        $query->where('data->question', 'like', "%{$search}%");
                    }),

                IconColumn::make('is_active')
                    ->label(__('lunarpanel.content::faq_item.table.is_active.label'))
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
            'index' => ListFaqItems::route('/'),
            'create' => CreateFaqItem::route('/create'),
            'edit' => EditFaqItem::route('/{record}/edit'),
        ];
    }
}
