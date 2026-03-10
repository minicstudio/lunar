<?php

namespace Lunar\Review\Filament\Resources;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Lunar\Admin\Filament\Resources\OrderResource\Pages\ManageOrder;
use Lunar\Admin\Support\Forms\Components\Attributes;
use Lunar\Admin\Support\Resources\BaseResource;
use Lunar\Review\Filament\Resources\ReviewResource\Pages\ListReview;
use Lunar\Review\Models\Review;

class ReviewResource extends BaseResource
{
    /**
     * The permission required to access this resource.
     */
    protected static ?string $permission = 'sales:reviews:manage';

    /**
     * The model associated with the blog category resource.
     */
    protected static ?string $model = Review::class;

    /**
     * Get the label for the resource.
     */
    public static function getLabel(): string
    {
        return __('lunarpanel.review::plugin.label');
    }

    /**
     * Get the plural label for the resource.
     */
    public static function getPluralLabel(): string
    {
        return __('lunarpanel.review::plugin.plural_label');
    }

    /**
     * Get the icon for the resource in the navigation.
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-star';
    }

    /**
     * Get the navigation group for the resource.
     */
    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.sales');
    }

    /**
     * Get the user selection form component.
     */
    public static function getUserFormComponent(?int $userId = null): Component
    {
        return Hidden::make('user_id')
            ->default($userId);
    }

    /**
     * Get the order selection form component.
     */
    public static function getOrderFormComponent(?int $orderId = null): Component
    {
        return Hidden::make('order_id')
            ->default($orderId);
    }

    /**
     * Get the model type selection form component.
     */
    public static function getModelTypeFormComponent(string $reviewableType = ''): Component
    {
        return Hidden::make('reviewable_type')
            ->default($reviewableType);
    }

    /**
     * Get the form component for the attribute data.
     */
    public static function getAttributeDataFormComponent(): Component
    {
        return Attributes::make()
            ->using(Review::class)
            ->hidden(function (callable $get) {
                return ! $get('reviewable_id');
            });
    }

    /**
     * Get the form component for the approved_at toggle.
     */
    public static function getApprovedAtToggle(): Component
    {
        return Toggle::make('approved_at')
            ->label(__('lunarpanel.review::plugin.form.approved_at'))
            ->onColor('success')
            ->offColor('gray')
            ->hidden(function (callable $get) {
                return ! $get('reviewable_id');
            })
            ->dehydrateStateUsing(function (?bool $state) {
                return $state ? now() : null;
            })
            ->afterStateHydrated(function (Toggle $component, $state) {
                $component->state($state !== false && $state !== null);
            });
    }

    /**
     * Get the form component for image uploads.
     */
    public static function getImageUploadComponent(): Component
    {
        return SpatieMediaLibraryFileUpload::make('review')
            ->label(__('lunarpanel.review::plugin.form.upload_images'))
            ->multiple()
            ->collection('reviews')
            ->maxFiles(config('lunar.review.max_files'))
            ->image()
            ->imageEditor();
    }

    /**
     * Get the default table schema for the resource.
     */
    protected static function getDefaultTable(Table $table): Table
    {
        return $table
            ->columns(static::getTableColumns())
            ->defaultSort('created_at', 'desc')
            ->filters([
                static::getApprovedAtFilter(),
                static::getRatingFilter(),
                static::getModelTypeFilter(),
            ])
            ->actions([
                Action::make('manageOrder')
                    ->label(__('lunarpanel.review::plugin.actions.manage_order.label'))
                    ->icon('heroicon-o-arrow-right')
                    ->visible(fn (Model $record): bool => (bool) $record->order)
                    ->url(fn (Model $record) => ManageOrder::getUrl(['record' => $record->order?->id])),
            ])
            ->recordUrl(function (Model $record): ?string {
                return $record->order ? ManageOrder::getUrl(['record' => $record->order->id]) : null;
            })
            ->bulkActions([
                //
            ]);
    }

    /**
     * Get the filter for the approved_at field.
     */
    public static function getApprovedAtFilter(): TernaryFilter
    {
        return TernaryFilter::make('approved_at')
            ->label(__('lunarpanel.review::plugin.filters.status.label'))
            ->placeholder(__('lunarpanel.review::plugin.filters.status.placeholder'))
            ->trueLabel(__('lunarpanel.review::plugin.filters.status.options.approved'))
            ->falseLabel(__('lunarpanel.review::plugin.filters.status.options.not_approved'))
            ->queries(
                true: fn (Builder $query) => $query->whereNotNull('approved_at'),
                false: fn (Builder $query) => $query->whereNull('approved_at'),
            );
    }

    /**
     * Get the rating filter.
     */
    public static function getRatingFilter(): Filter
    {
        return Filter::make('rating')
            ->form([
                Select::make('rating')
                    ->label(__('lunarpanel.review::plugin.filters.rating.label'))
                    ->options([
                        '1' => '1',
                        '2' => '2',
                        '3' => '3',
                        '4' => '4',
                        '5' => '5',
                    ]),
            ])
            ->query(function (Builder $query, array $data): Builder {
                if (! empty($data['rating'])) {
                    return $query->where('attribute_data->rating->value', $data['rating']);
                }

                return $query;
            })
            ->indicateUsing(function (array $data): ?string {
                if (! $data['rating']) {
                    return null;
                }

                return __('lunarpanel.review::plugin.filters.rating.indicator', ['rating' => $data['rating']]);
            });
    }

    /**
     * Get the filter for the reviewable_type field.
     */
    public static function getModelTypeFilter(): SelectFilter
    {
        return SelectFilter::make('reviewable_type')
            ->options(function () {
                $availableTypes = config('lunar.review.available_types', []);

                return collect($availableTypes)
                    ->mapWithKeys(fn ($class) => [$class::morphName() => Str::headline($class::morphName())])
                    ->all();
            })
            ->attribute('reviewable_type')
            ->multiple();
    }

    /**
     * Get the columns for the table.
     */
    public static function getTableColumns(): array
    {
        return [
            static::getOrderReferenceTableColumn(),
            static::getModelTypeTableColumn(),
            static::getModelNameTableColumn(__('lunarpanel.review::plugin.table.model.name.label')),
            static::getRatingTableColumn(),
            static::getApprovedAtTableColumn(),
        ];
    }

    /**
     * Get the column for the order reference in the table.
     */
    public static function getOrderReferenceTableColumn(): Column
    {
        return TextColumn::make('order.reference')
            ->label(__('lunarpanel.review::plugin.table.order_reference.label'))
            ->getStateUsing(fn (Model $record) => $record->order?->reference)
            ->searchable();
    }

    /**
     * Get the column for the model type in the table.
     */
    public static function getModelTypeTableColumn(): Column
    {
        return TextColumn::make('reviewable_type')
            ->label(__('lunarpanel.review::plugin.table.model.type.label'))
            ->formatStateUsing(function (?string $state) {
                return Str::headline($state);
            })
            ->searchable();
    }

    /**
     * Get the column for the model name in the table.
     */
    public static function getModelNameTableColumn(string $label): Column
    {
        return TextColumn::make('reviewable_id')
            ->label($label)
            ->getStateUsing(function (Model $record) {
                if (! $modelClass = static::getResolvedModelClass($record->reviewable_type)) {
                    return null;
                }

                $model = $modelClass::find($record->reviewable_id);

                if (! $model) {
                    return null;
                }

                return $model->getName();
            })
            ->searchable();
    }

    /**
     * Get the column for the rating in the table.
     */
    public static function getRatingTableColumn(): Column
    {
        return TextColumn::make('attribute_data.rating')
            ->label(__('lunarpanel.review::plugin.table.rating.label'))
            ->getStateUsing(fn (Model $record) => $record->translateAttribute('rating'))
            ->searchable();
    }

    /**
     * Get the column for the approved_at field in the table.
     */
    public static function getApprovedAtTableColumn(): Column
    {
        return TextColumn::make('approved_at')
            ->label(__('lunarpanel.review::plugin.table.approved_at.label'))
            ->badge()
            ->getStateUsing(fn (Model $record) => $record->approved_at ? 'approved' : 'not_approved')
            ->formatStateUsing(fn (string $state) => __('lunarpanel.review::plugin.table.approved_at.states.'.$state))
            ->color(
                fn (string $state): string => match ($state) {
                    'approved' => 'success',
                    'not_approved' => 'warning',
                }
            );
    }

    /**
     * Resolve and return the model class from the record's reviewable_type.
     *
     * This method checks the morph_map configuration and verifies that the class exists.
     *
     * @return string|null The fully qualified model class name, or null if not found.
     */
    public static function getResolvedModelClass(string $modelKey): ?string
    {
        $morphMap = Relation::morphMap();

        if (! $modelKey || ! isset($morphMap[$modelKey])) {
            return null;
        }

        $modelClass = $morphMap[$modelKey];

        return class_exists($modelClass) ? $modelClass : null;
    }

    /**
     * Get the pages for the resource.
     */
    public static function getDefaultPages(): array
    {
        return [
            'index' => ListReview::route('/'),
        ];
    }
}
