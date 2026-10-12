<?php

namespace Lunar\Feedback\Filament\Resources;

use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Lunar\Admin\Filament\Resources\OrderResource\Pages\ManageOrder;
use Lunar\Admin\Support\Resources\BaseResource;
use Lunar\Feedback\Filament\Resources\FeedbackResource\Pages\ListFeedback;
use Lunar\Feedback\Models\Feedback;

class FeedbackResource extends BaseResource
{
    /**
     * The model associated with the resource.
     */
    protected static ?string $model = Feedback::class;

    /**
     * Get the label for the resource.
     */
    public static function getLabel(): string
    {
        return __('lunarpanel.feedback::plugin.label');
    }

    /**
     * Get the plural label for the resource.
     */
    public static function getPluralLabel(): string
    {
        return __('lunarpanel.feedback::plugin.plural_label');
    }

    /**
     * Get the icon for the resource in the navigation.
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-chat-bubble-left-right';
    }

    /**
     * Get the navigation group for the resource.
     */
    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.sales');
    }

    /**
     * Feedback rows are only written by the storefront.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Feedback rows are read-only in the admin.
     */
    public static function canEdit(Model $record): bool
    {
        return false;
    }

    /**
     * Feedback rows are read-only in the admin.
     */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /**
     * Feedback rows are read-only in the admin.
     */
    public static function canDeleteAny(): bool
    {
        return false;
    }

    /**
     * Get the default table schema for the resource.
     */
    protected static function getDefaultTable(Table $table): Table
    {
        return $table
            ->columns(static::getTableColumns())
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['order']))
            ->filters([
                static::getTypeFilter(),
                static::getScoreFilter(),
            ])
            ->recordUrl(function (Feedback $record): ?string {
                return $record->order_id ? ManageOrder::getUrl(['record' => $record->order_id]) : null;
            })
            ->recordActions([])
            ->toolbarActions([]);
    }

    /**
     * Get the table columns for the resource.
     *
     * @return array<int, Column>
     */
    public static function getTableColumns(): array
    {
        return [
            TextColumn::make('type')
                ->label(__('lunarpanel.feedback::plugin.table.type.label'))
                ->badge()
                ->searchable(),
            TextColumn::make('score')
                ->label(__('lunarpanel.feedback::plugin.table.score.label'))
                ->sortable(),
            TextColumn::make('order.reference')
                ->label(__('lunarpanel.feedback::plugin.table.order_reference.label'))
                ->placeholder('-')
                ->searchable(),
            TextColumn::make('comment')
                ->label(__('lunarpanel.feedback::plugin.table.comment.label'))
                ->placeholder('-')
                ->limit(80)
                ->wrap(),
            TextColumn::make('locale')
                ->label(__('lunarpanel.feedback::plugin.table.locale.label')),
            TextColumn::make('created_at')
                ->label(__('lunarpanel.feedback::plugin.table.created_at.label'))
                ->dateTime()
                ->sortable(),
        ];
    }

    /**
     * Get the filter for the stored feedback types.
     */
    public static function getTypeFilter(): SelectFilter
    {
        return SelectFilter::make('type')
            ->label(__('lunarpanel.feedback::plugin.filters.type.label'))
            ->options(fn (): array => Feedback::query()
                ->distinct()
                ->orderBy('type')
                ->pluck('type', 'type')
                ->all());
    }

    /**
     * Get the filter for the configured score scale.
     */
    public static function getScoreFilter(): SelectFilter
    {
        $scale = range(
            (int) config('lunar.feedback.scale_min'),
            (int) config('lunar.feedback.scale_max'),
        );

        return SelectFilter::make('score')
            ->label(__('lunarpanel.feedback::plugin.filters.score.label'))
            ->options(array_combine($scale, $scale));
    }

    /**
     * Get the pages for the resource.
     */
    public static function getDefaultPages(): array
    {
        return [
            'index' => ListFeedback::route('/'),
        ];
    }
}
