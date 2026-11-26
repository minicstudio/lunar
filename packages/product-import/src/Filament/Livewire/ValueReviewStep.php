<?php

namespace Lunar\ProductImport\Filament\Livewire;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Lunar\Models\Attribute;
use Lunar\Models\Brand;
use Lunar\Models\Collection;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Currency;
use Lunar\Models\Discount;
use Lunar\Models\ProductOption;
use Lunar\Models\ProductOptionValue;
use Lunar\ProductImport\Actions\ContinueToPreview;
use Lunar\ProductImport\Actions\ContinueToStaging;
use Lunar\ProductImport\Actions\DecideValue;
use Lunar\ProductImport\Actions\ReturnToMapping;
use Lunar\ProductImport\Enums\MatchType;
use Lunar\ProductImport\Enums\ValueAction;
use Lunar\ProductImport\Enums\ValueType;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportValue;
use Lunar\ProductImport\Normalizing\DateParser;
use Lunar\ProductImport\Normalizing\RowDiscount;
use Lunar\ProductImport\Support\TargetFieldRegistry;
use Lunar\ProductImport\Taxonomy\CatalogSnapshot;

/**
 * The review of categories, brands, options, option values, properties, and
 * discounts: a tab per type, the match of each value, and a decision per
 * value. A discount shows its value and dates, and is created with an
 * edited name, kind, value, start, and end.
 */
class ValueReviewStep extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    #[Locked]
    public int $importId;

    public string $type = 'category';

    /**
     * Category paths of the group, built once per request.
     *
     * @var ?array<int, string>
     */
    protected ?array $paths = null;

    /**
     * Product option names, loaded once per request.
     *
     * @var ?array<int, string>
     */
    protected ?array $optionNames = null;

    public function mount(ProductImport $import): void
    {
        $this->importId = $import->id;
    }

    public function import(): ProductImport
    {
        return ProductImport::query()->findOrFail($this->importId);
    }

    public function setType(string $type): void
    {
        $this->type = ValueType::tryFrom($type)?->value ?? ValueType::Category->value;
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => ProductImportValue::query()
                ->where('product_import_id', $this->importId)
                ->where('type', $this->type))
            ->defaultSort('source_value')
            ->columns([
                TextColumn::make('source_value')
                    ->label(__('lunarpanel.product-import::ui.values.value'))
                    ->formatStateUsing(fn (string $state, ProductImportValue $record): string => $record->type === ValueType::Discount ? $this->discountText($record->details) ?? $state : $state)
                    ->searchable()
                    ->description(fn (ProductImportValue $record): ?string => $record->type === ValueType::OptionValue ? $this->optionName($record->option_key) : null),
                TextColumn::make('sheets')
                    ->label(__('lunarpanel.product-import::ui.values.sheets'))
                    ->state(fn (ProductImportValue $record): string => implode(', ', $record->sheets ?? [])),
                TextColumn::make('product_count')
                    ->label(__('lunarpanel.product-import::ui.values.products'))
                    ->sortable(),
                TextColumn::make('variant_count')
                    ->label(__('lunarpanel.product-import::ui.values.variants'))
                    ->visible(fn (): bool => in_array($this->type, [ValueType::OptionValue->value, ValueType::Discount->value], true)),
                TextColumn::make('match_type')
                    ->label(__('lunarpanel.product-import::ui.values.match'))
                    ->badge()
                    ->formatStateUsing(fn (?MatchType $state): string => $state ? __('lunarpanel.product-import::ui.values.matches.'.$state->value) : '-')
                    ->color(fn (?MatchType $state): string => match ($state) {
                        MatchType::Exact => 'success',
                        MatchType::Partial => 'info',
                        MatchType::Similar => 'warning',
                        default => 'gray',
                    })
                    ->description(fn (ProductImportValue $record): ?string => match (true) {
                        $record->match_type === MatchType::Similar => $this->recordName($record, $record->matched_id).' · '.(int) round(($record->score ?? 0) * 100).'%',
                        $record->type === ValueType::Discount && $record->matched_id !== null => $this->recordName($record, $record->matched_id),
                        default => null,
                    }),
                TextColumn::make('decision')
                    ->label(__('lunarpanel.product-import::ui.values.decision'))
                    ->state(fn (ProductImportValue $record): string => $this->decisionText($record))
                    ->color(fn (ProductImportValue $record): ?string => $record->needsDecision() ? 'warning' : null)
                    ->wrap(),
            ])
            ->recordActions([
                $this->useExistingAction(),
                $this->createAction(),
                Action::make('skip')
                    ->label(__('lunarpanel.product-import::ui.values.skip'))
                    ->color('gray')
                    ->action(fn (ProductImportValue $record) => $this->decide(fn () => app(DecideValue::class)->skip($record))),
            ])
            ->paginated([25, 50, 100]);
    }

    protected function useExistingAction(): Action
    {
        return Action::make('useExisting')
            ->label(__('lunarpanel.product-import::ui.values.use_existing'))
            ->fillForm(fn (ProductImportValue $record): array => ['record' => $record->target_id ?? $record->matched_id])
            ->schema(fn (ProductImportValue $record): array => [
                Select::make('record')
                    ->label(__('lunarpanel.product-import::ui.values.record'))
                    ->options(fn (): array => $this->recordOptions($record))
                    ->searchable()
                    ->required(),
            ])
            ->action(fn (array $data, ProductImportValue $record) => $this->decide(
                fn () => app(DecideValue::class)->useExisting($record, (int) $data['record'])
            ));
    }

    protected function createAction(): Action
    {
        return Action::make('create')
            ->label(__('lunarpanel.product-import::ui.values.create'))
            ->visible(fn (ProductImportValue $record): bool => $record->type !== ValueType::Property)
            ->fillForm(fn (ProductImportValue $record): array => $record->type === ValueType::Discount
                ? $this->discountForm($record)
                : [
                    'name' => $record->create_name ?? ($record->type === ValueType::Category ? Arr::last($record->path ?? []) : null) ?? $record->source_value,
                    'parent' => $record->parent_id,
                ])
            ->schema(fn (ProductImportValue $record): array => $record->type === ValueType::Discount ? $this->discountSchema() : array_values(array_filter([
                TextInput::make('name')
                    ->label(__('lunarpanel.product-import::ui.values.name'))
                    ->helperText($record->type === ValueType::Category ? __('lunarpanel.product-import::ui.values.name_help') : null)
                    ->required(),
                $record->type === ValueType::Category
                    ? Select::make('parent')
                        ->label(__('lunarpanel.product-import::ui.values.parent'))
                        ->options(fn (): array => $this->recordOptions($record))
                        ->placeholder(__('lunarpanel.product-import::ui.values.root'))
                        ->searchable()
                    : null,
            ])))
            ->action(fn (array $data, ProductImportValue $record) => $this->decide(
                fn () => app(DecideValue::class)->create(
                    $record,
                    (string) $data['name'],
                    filled($data['parent'] ?? null) ? (int) $data['parent'] : null,
                    $record->type === ValueType::Discount ? $this->discountDetails($data) : null,
                )
            ));
    }

    /**
     * The create form of a discount: its name and the kind, value (an amount
     * in major units), start, and end to create, prefilled from the file.
     *
     * @return array<string, mixed>
     */
    protected function discountForm(ProductImportValue $value): array
    {
        $discount = RowDiscount::fromArray($value->create_details ?? $value->details);

        return [
            'name' => $value->create_name ?? $value->source_value,
            'kind' => $discount?->kind ?? RowDiscount::PERCENT,
            'value' => match (true) {
                $discount === null => null,
                $discount->kind === RowDiscount::AMOUNT => number_format((int) $discount->value / $this->currencyFactor(), $this->currencyDecimals(), '.', ''),
                default => $discount->valueText(),
            },
            'starts_at' => $discount?->startsAt,
            'ends_at' => $discount?->endsAt,
        ];
    }

    /**
     * The fields of a discount to create.
     *
     * @return list<Field>
     */
    protected function discountSchema(): array
    {
        return [
            TextInput::make('name')
                ->label(__('lunarpanel.product-import::ui.values.name'))
                ->required()
                ->maxLength(255),
            Select::make('kind')
                ->label(__('lunarpanel.product-import::ui.discounts.kind'))
                ->options([
                    RowDiscount::PERCENT => __('lunarpanel.product-import::ui.discounts.kinds.percent'),
                    RowDiscount::AMOUNT => __('lunarpanel.product-import::ui.discounts.kinds.amount'),
                ])
                ->required(),
            TextInput::make('value')
                ->label(__('lunarpanel.product-import::ui.discounts.value'))
                ->helperText(__('lunarpanel.product-import::ui.discounts.value_help', ['currency' => (string) Currency::getDefault()?->code]))
                ->numeric()
                ->minValue(0)
                ->required(),
            DateTimePicker::make('starts_at')
                ->label(__('lunarpanel.product-import::ui.discounts.starts_at'))
                ->helperText(__('lunarpanel.product-import::ui.discounts.starts_at_help')),
            DateTimePicker::make('ends_at')
                ->label(__('lunarpanel.product-import::ui.discounts.ends_at'))
                ->helperText(__('lunarpanel.product-import::ui.discounts.ends_at_help')),
        ];
    }

    /**
     * The details of a discount to create from its form: the percent, or the
     * amount in minor units of the default currency.
     *
     * @param  array<string, mixed>  $data
     * @return array{kind: string, value: int|float, starts_at: ?string, ends_at: ?string}
     */
    protected function discountDetails(array $data): array
    {
        $kind = (string) ($data['kind'] ?? RowDiscount::PERCENT);
        $value = (float) ($data['value'] ?? 0);
        $date = fn (mixed $date): ?string => filled($date) ? Carbon::parse((string) $date)->format(DateParser::STORED_FORMAT) : null;

        return [
            'kind' => $kind,
            'value' => $kind === RowDiscount::AMOUNT ? (int) round($value * $this->currencyFactor()) : round($value, 4),
            'starts_at' => $date($data['starts_at'] ?? null),
            'ends_at' => $date($data['ends_at'] ?? null),
        ];
    }

    /**
     * A discount's value and dates in words, from its details.
     *
     * @param  ?array<string, mixed>  $details
     */
    protected function discountText(?array $details): ?string
    {
        return RowDiscount::fromArray($details)?->describe($this->currencyFactor(), Currency::getDefault()?->code);
    }

    /**
     * The default currency's minor units per unit.
     */
    protected function currencyFactor(): int
    {
        return max(1, (int) (Currency::getDefault()?->factor ?? 100));
    }

    /**
     * The default currency's decimals.
     */
    protected function currencyDecimals(): int
    {
        return (int) round(log10($this->currencyFactor()));
    }

    public function continueAction(): Action
    {
        return Action::make('continue')
            ->label(fn (): string => $this->openDecisions() > 0
                ? trans_choice('lunarpanel.product-import::ui.values.open_decisions', $this->openDecisions(), ['count' => $this->openDecisions()])
                : __('lunarpanel.product-import::ui.actions.continue'))
            ->disabled(fn (): bool => $this->openDecisions() > 0)
            ->action(fn () => $this->decide(fn () => app(ContinueToPreview::class)->handle($this->import()), true));
    }

    public function backAction(): Action
    {
        return Action::make('back')
            ->label(__('lunarpanel.product-import::ui.actions.back_to_mapping'))
            ->color('gray')
            ->action(fn () => $this->decide(fn () => app(ReturnToMapping::class)->handle($this->import()), true));
    }

    /**
     * Change the collection group: the rows are staged again, keeping the
     * decisions that still apply. Separators belong to the columns and are
     * changed in the mapping step.
     */
    public function reviewOptionsAction(): Action
    {
        return Action::make('reviewOptions')
            ->label(__('lunarpanel.product-import::ui.values.change_options'))
            ->color('gray')
            ->fillForm(fn (): array => [
                'collection_group_id' => $this->import()->importOptions()->collectionGroupId,
            ])
            ->schema([
                Select::make('collection_group_id')
                    ->label(__('lunarpanel.product-import::ui.mapping.collection_group'))
                    ->options(fn (): array => CollectionGroup::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->required(),
            ])
            ->action(function (array $data): void {
                $this->decide(function () use ($data): void {
                    $import = $this->import();
                    $options = $import->importOptions();
                    $options->collectionGroupId = (int) $data['collection_group_id'];

                    app(ReturnToMapping::class)->handle($import);
                    $import->refresh()->forceFill(['options' => $options->toArray()])->save();
                    app(ContinueToStaging::class)->handle($import);
                }, true);
            });
    }

    /**
     * How many values still need a decision.
     */
    public function openDecisions(): int
    {
        return app(ContinueToPreview::class)->openDecisions($this->import());
    }

    /**
     * The counts shown above the table.
     *
     * @return array{existing: int, similar: int, new: int, without_category: int}
     */
    public function stats(): array
    {
        $counts = ProductImportValue::query()
            ->where('product_import_id', $this->importId)
            ->selectRaw('match_type, count(*) as total')
            ->groupBy('match_type')
            ->pluck('total', 'match_type');

        return [
            'existing' => (int) ($counts[MatchType::Exact->value] ?? 0) + (int) ($counts[MatchType::Partial->value] ?? 0),
            'similar' => (int) ($counts[MatchType::Similar->value] ?? 0),
            'new' => (int) ($counts[MatchType::None->value] ?? 0),
            'without_category' => (int) ($this->import()->stats['review']['products_without_category'] ?? 0),
            'properties_not_imported' => ProductImportValue::query()
                ->where('product_import_id', $this->importId)
                ->where('type', ValueType::Property)
                ->where('action', ValueAction::Skip)
                ->count(),
        ];
    }

    public function render(): View
    {
        return view('lunarpanel.product-import::livewire.value-review-step', [
            'stats' => $this->stats(),
            'types' => ValueType::cases(),
        ]);
    }

    /**
     * The records a value may use: brands, categories of the group (with
     * their path), or the values of its option.
     *
     * @return array<int, string>
     */
    protected function recordOptions(ProductImportValue $value): array
    {
        $locale = app(TargetFieldRegistry::class)->defaultLanguage();

        return match ($value->type) {
            ValueType::Brand => Brand::query()->orderBy('name')->pluck('name', 'id')->all(),
            ValueType::Category => $this->categoryPaths($locale),
            ValueType::Option => app(TargetFieldRegistry::class)->options(),
            ValueType::OptionValue => ($optionId = $value->optionOf()) !== null
                ? ProductOptionValue::query()
                    ->where('product_option_id', $optionId)
                    ->get()
                    ->mapWithKeys(fn (ProductOptionValue $optionValue): array => [$optionValue->id => (string) $optionValue->translate('name')])
                    ->all()
                : [],
            ValueType::Property => app(TargetFieldRegistry::class)->textAttributes((int) Str::after($value->option_key, 'type:'))
                ->mapWithKeys(fn ($attribute): array => [$attribute->id => (string) ($attribute->translate('name') ?: $attribute->handle)])
                ->all(),
            ValueType::Discount => $this->discountOptions(),
        };
    }

    /**
     * The eligible discounts a discount may use, with their value and dates.
     *
     * @return array<int, string>
     */
    protected function discountOptions(): array
    {
        $snapshot = new CatalogSnapshot;
        $snapshot->loadDiscounts();

        return collect($snapshot->discounts)
            ->map(fn (array $discount): string => $discount['name'].' ('.$this->discountText($discount).')')
            ->sort()
            ->all();
    }

    /**
     * Every category of the import's group with its full path.
     *
     * @return array<int, string>
     */
    protected function categoryPaths(string $locale): array
    {
        if ($this->paths !== null) {
            return $this->paths;
        }

        $snapshot = CatalogSnapshot::load($this->import()->importOptions()->collectionGroupId, [], $locale);

        return $this->paths = collect(array_keys($snapshot->collections))
            ->mapWithKeys(fn (int $id): array => [$id => implode(' > ', $snapshot->collectionPath($id))])
            ->sort()
            ->all();
    }

    /**
     * The display name of a record of the value's type.
     */
    protected function recordName(ProductImportValue $value, ?int $id): string
    {
        if ($id === null) {
            return '-';
        }

        return match ($value->type) {
            ValueType::Category => $this->categoryPaths(app(TargetFieldRegistry::class)->defaultLanguage())[$id] ?? '#'.$id,
            ValueType::Brand => (string) (Brand::query()->find($id)?->name ?? '#'.$id),
            ValueType::Option => (string) (ProductOption::query()->find($id)?->translate('name') ?? '#'.$id),
            ValueType::OptionValue => (string) (ProductOptionValue::query()->find($id)?->translate('name') ?? '#'.$id),
            ValueType::Property => (string) (Attribute::query()->find($id)?->translate('name') ?? '#'.$id),
            ValueType::Discount => (string) (Discount::query()->find($id)?->name ?? '#'.$id),
        };
    }

    /**
     * What the value will become.
     */
    protected function decisionText(ProductImportValue $value): string
    {
        return match ($value->action) {
            ValueAction::UseExisting => __('lunarpanel.product-import::ui.values.decisions.use_existing', ['name' => $this->recordName($value, $value->target_id)]),
            ValueAction::Create => match (true) {
                $value->type === ValueType::Category && $value->parent_id !== null => __('lunarpanel.product-import::ui.values.decisions.create_under', ['name' => $value->create_name, 'parent' => $this->recordName($value, $value->parent_id)]),
                $value->type === ValueType::Discount => __('lunarpanel.product-import::ui.values.decisions.create_discount', ['name' => $value->create_name, 'discount' => (string) $this->discountText($value->create_details ?? $value->details)]),
                default => __('lunarpanel.product-import::ui.values.decisions.create', ['name' => $value->create_name]),
            },
            ValueAction::Skip => match ($value->type) {
                ValueType::OptionValue => trans_choice('lunarpanel.product-import::ui.values.decisions.skip_variants', $value->variant_count, ['count' => $value->variant_count]),
                ValueType::Option => __('lunarpanel.product-import::ui.values.decisions.skip_option'),
                ValueType::Property => __('lunarpanel.product-import::ui.values.decisions.skip_property'),
                ValueType::Discount => __('lunarpanel.product-import::ui.values.decisions.skip_discount'),
                default => __('lunarpanel.product-import::ui.values.decisions.skip'),
            },
            null => __('lunarpanel.product-import::ui.values.decisions.open'),
        };
    }

    /**
     * The option name of an option key.
     */
    protected function optionName(string $optionKey): string
    {
        if (Str::startsWith($optionKey, 'option:')) {
            $this->optionNames ??= app(TargetFieldRegistry::class)->options();

            return (string) ($this->optionNames[(int) Str::after($optionKey, 'option:')] ?? $optionKey);
        }

        if (Str::startsWith($optionKey, 'name:')) {
            $name = ProductImportValue::query()
                ->where('product_import_id', $this->importId)
                ->where('type', ValueType::Option)
                ->where('normalized_key', Str::after($optionKey, 'name:'))
                ->value('source_value');

            return __('lunarpanel.product-import::ui.values.cell_option', ['name' => $name ?? Str::after($optionKey, 'name:')]);
        }

        return __('lunarpanel.product-import::ui.values.new_option', ['name' => Str::after($optionKey, 'new:')]);
    }

    /**
     * Run a decision and report a refusal.
     */
    protected function decide(callable $decision, bool $changesStep = false): void
    {
        try {
            $decision();
        } catch (ImportActionRefused $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return;
        }

        if ($changesStep) {
            $this->dispatch('product-import-changed');
        }
    }
}
