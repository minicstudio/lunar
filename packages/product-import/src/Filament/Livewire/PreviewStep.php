<?php

namespace Lunar\ProductImport\Filament\Livewire;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Lunar\Models\Currency;
use Lunar\ProductImport\Actions\IncludeRows;
use Lunar\ProductImport\Actions\ReturnToMapping;
use Lunar\ProductImport\Actions\ReturnToValueReview;
use Lunar\ProductImport\Actions\SkipRows;
use Lunar\ProductImport\Actions\StartImport;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\RowAction;
use Lunar\ProductImport\Enums\RowRole;
use Lunar\ProductImport\Enums\RowSkipReason;
use Lunar\ProductImport\Enums\RowStatus;
use Lunar\ProductImport\Enums\TargetField;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\ProductImport\Filament\Actions\CorrectRowAction;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Normalizing\RowDiscount;
use Lunar\ProductImport\Queries\PreviewSummary;

/**
 * The preview: the products and variants the import will create or update,
 * with each variant's price and discount, totals, filters, row corrections,
 * skips, and the start action.
 */
class PreviewStep extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    #[Locked]
    public int $importId;

    public string $filter = PreviewSummary::FILTER_ALL;

    public function mount(ProductImport $import): void
    {
        $this->importId = $import->id;
    }

    public function import(): ProductImport
    {
        return ProductImport::query()->findOrFail($this->importId);
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, [
            PreviewSummary::FILTER_ALL, PreviewSummary::FILTER_NEW, PreviewSummary::FILTER_UPDATE,
            PreviewSummary::FILTER_PROBLEMS, PreviewSummary::FILTER_SKIPPED,
        ], true) ? $filter : PreviewSummary::FILTER_ALL;

        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        $factor = (int) (Currency::getDefault()?->factor ?? 100);

        return $table
            ->query(fn () => app(PreviewSummary::class)->rows($this->import(), $this->filter)->with('sheet'))
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw('case when role = ? then 0 else 1 end', [RowRole::Product->value])
                ->orderBy('product_import_sheet_id')
                ->orderBy('row_number'))
            ->groups([
                Group::make('group_key')
                    ->label(__('lunarpanel.product-import::ui.preview.product'))
                    ->getTitleFromRecordUsing(fn (ProductImportRow $record): string => $this->groupTitle($record))
                    ->collapsible(),
            ])
            ->defaultGroup('group_key')
            ->columns([
                TextColumn::make('row_number')
                    ->label(__('lunarpanel.product-import::ui.preview.row'))
                    ->formatStateUsing(fn (ProductImportRow $record): string => $record->sheet->name.' · '.$record->row_number),
                TextColumn::make('match_value')
                    ->label(__('lunarpanel.product-import::ui.preview.key'))
                    ->searchable()
                    ->description(fn (ProductImportRow $record): ?string => match (true) {
                        $record->role === RowRole::Product => __('lunarpanel.product-import::ui.preview.product_row'),
                        $record->action === RowAction::Update => __('lunarpanel.product-import::ui.preview.updates_variant'),
                        $record->matched_product_id !== null => __('lunarpanel.product-import::ui.preview.new_variant_of', ['id' => $record->matched_product_id]),
                        default => null,
                    }),
                TextColumn::make('options')
                    ->label(__('lunarpanel.product-import::ui.preview.options'))
                    ->state(fn (ProductImportRow $record): string => implode(' / ', array_filter([
                        ...$record->effectiveRow()->withPrefix('variant.option.'),
                        ...array_values($record->effectiveRow()->namedOptions()),
                    ], fn (mixed $value): bool => filled($value)))),
                TextColumn::make('price')
                    ->label(__('lunarpanel.product-import::ui.preview.price'))
                    ->state(function (ProductImportRow $record) use ($factor): ?string {
                        $price = $record->effectiveRow()->get(TargetField::VariantPrice->value);

                        return $price === null ? null : number_format($price / $factor, 2, ',', '.');
                    })
                    ->placeholder('-'),
                TextColumn::make('discount')
                    ->label(__('lunarpanel.product-import::ui.preview.discount'))
                    ->state(function (ProductImportRow $record) use ($factor): ?string {
                        $discount = $record->isVariantRow() ? RowDiscount::for($record->effectiveRow()) : null;

                        // A discount that ended is not imported.
                        return $discount !== null && ! $discount->hasEnded() ? $discount->describe($factor, Currency::getDefault()?->code) : null;
                    })
                    ->placeholder('-'),
                TextColumn::make('stock')
                    ->label(__('lunarpanel.product-import::ui.preview.stock'))
                    ->state(fn (ProductImportRow $record): mixed => $record->effectiveRow()->get(TargetField::VariantStock->value))
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label(__('lunarpanel.product-import::ui.list.status'))
                    ->badge()
                    ->formatStateUsing(fn (RowStatus $state): string => __('lunarpanel.product-import::ui.preview.statuses.'.$state->value))
                    ->color(fn (RowStatus $state): string => match ($state) {
                        RowStatus::Valid, RowStatus::Imported => 'success',
                        RowStatus::Warning => 'warning',
                        RowStatus::Error, RowStatus::Failed => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('messages')
                    ->label(__('lunarpanel.product-import::ui.preview.messages'))
                    ->state(fn (ProductImportRow $record): string => collect($record->messages ?? [])->pluck('message')->implode(' · '))
                    ->wrap(),
            ])
            ->recordActions([
                CorrectRowAction::make()
                    ->after(fn () => $this->resetTable()),
                Action::make('skipRow')
                    ->label(__('lunarpanel.product-import::ui.preview.skip'))
                    ->color('gray')
                    ->visible(fn (ProductImportRow $record): bool => $record->status !== RowStatus::Skipped)
                    ->action(fn (ProductImportRow $record) => $this->run(fn () => app(SkipRows::class)->handle($this->import(), [$record->id]))),
                Action::make('includeRow')
                    ->label(__('lunarpanel.product-import::ui.preview.include'))
                    ->color('gray')
                    ->visible(fn (ProductImportRow $record): bool => $record->skip_reason === RowSkipReason::Staff)
                    ->action(fn (ProductImportRow $record) => $this->run(fn () => app(IncludeRows::class)->handle($this->import(), [$record->id]))),
                Action::make('includeRowAs')
                    ->label(__('lunarpanel.product-import::ui.preview.include'))
                    ->color('gray')
                    ->visible(fn (ProductImportRow $record): bool => $record->skip_reason === RowSkipReason::RowType)
                    ->modalHeading(fn (ProductImportRow $record): string => __('lunarpanel.product-import::ui.preview.include_heading', ['sheet' => $record->sheet->name, 'row' => $record->row_number]))
                    ->modalSubmitActionLabel(__('lunarpanel.product-import::ui.preview.include'))
                    ->schema([
                        Select::make('role')
                            ->label(__('lunarpanel.product-import::ui.preview.include_role'))
                            ->options(collect([RowRole::ProductAndVariant, RowRole::Product, RowRole::Variant])
                                ->mapWithKeys(fn (RowRole $role): array => [$role->value => __('lunarpanel.product-import::ui.preview.roles.'.$role->value)])
                                ->all())
                            ->default(RowRole::ProductAndVariant->value)
                            ->selectablePlaceholder(false)
                            ->required(),
                    ])
                    ->action(function (ProductImportRow $record, array $data): void {
                        $this->run(fn () => app(IncludeRows::class)->handle($this->import(), [$record->id], RowRole::from($data['role'])));

                        // A new category, brand, or option value of the row needs a decision first.
                        if ($this->import()->status === ImportStatus::ReviewingValues) {
                            Notification::make()->warning()->title(__('lunarpanel.product-import::ui.preview.decide_included_values'))->send();
                            $this->dispatch('product-import-changed');
                        }
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('skipSelected')
                    ->label(__('lunarpanel.product-import::ui.preview.skip_selected'))
                    ->action(fn (EloquentCollection $records) => $this->run(fn () => app(SkipRows::class)->handle($this->import(), $records->modelKeys()))),
            ])
            ->paginated([50, 100, 250]);
    }

    public function skipErrorsAction(): Action
    {
        return Action::make('skipErrors')
            ->label(__('lunarpanel.product-import::ui.preview.skip_errors'))
            ->color('gray')
            ->visible(fn (): bool => $this->totals()['errors'] > 0)
            ->requiresConfirmation()
            ->action(fn () => $this->run(fn () => app(SkipRows::class)->errors($this->import())));
    }

    public function startAction(): Action
    {
        return Action::make('start')
            ->label(fn (): string => app(StartImport::class)->label($this->import()))
            ->disabled(fn (): bool => $this->totals()['importable'] === 0)
            ->requiresConfirmation()
            ->modalDescription(fn (): string => $this->totals()['errors'] > 0
                ? trans_choice('lunarpanel.product-import::ui.preview.confirm_with_errors', $this->totals()['errors'], ['count' => $this->totals()['errors']])
                : __('lunarpanel.product-import::ui.preview.confirm'))
            ->action(fn () => $this->run(fn () => app(StartImport::class)->handle($this->import(), confirmed: true), true));
    }

    public function backToValuesAction(): Action
    {
        return Action::make('backToValues')
            ->label(__('lunarpanel.product-import::ui.actions.back_to_values'))
            ->color('gray')
            ->visible(fn (): bool => $this->import()->values()->exists())
            ->action(fn () => $this->run(fn () => app(ReturnToValueReview::class)->handle($this->import()), true));
    }

    public function backToMappingAction(): Action
    {
        return Action::make('backToMapping')
            ->label(__('lunarpanel.product-import::ui.actions.back_to_mapping'))
            ->color('gray')
            ->action(fn () => $this->run(fn () => app(ReturnToMapping::class)->handle($this->import()), true));
    }

    /**
     * @return array{products: int, new: int, updated: int, variants: int, warnings: int, errors: int, skipped: int, importable: int}
     */
    public function totals(): array
    {
        return app(PreviewSummary::class)->totals($this->import());
    }

    public function render(): View
    {
        return view('lunarpanel.product-import::livewire.preview-step', [
            'totals' => $this->totals(),
            'filters' => [
                PreviewSummary::FILTER_ALL, PreviewSummary::FILTER_NEW, PreviewSummary::FILTER_UPDATE,
                PreviewSummary::FILTER_PROBLEMS, PreviewSummary::FILTER_SKIPPED,
            ],
        ]);
    }

    /**
     * The title of a product group: its name and whether it is new or updates a product.
     */
    protected function groupTitle(ProductImportRow $row): string
    {
        $name = collect($row->effectiveRow()->withPrefix(TargetField::ProductName->value.'.'))->filter()->first()
            ?? $row->match_value
            ?? $row->group_key;

        return $row->matched_product_id !== null
            ? __('lunarpanel.product-import::ui.preview.update_of', ['name' => $name, 'id' => $row->matched_product_id])
            : __('lunarpanel.product-import::ui.preview.new_product', ['name' => $name]);
    }

    /**
     * Run an action, report a refusal, and refresh.
     */
    protected function run(callable $action, bool $changesStep = false): void
    {
        try {
            $action();
        } catch (ImportActionRefused $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return;
        }

        $this->resetTable();

        if ($changesStep) {
            $this->dispatch('product-import-changed');
        }
    }
}
