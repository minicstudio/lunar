<?php

namespace Lunar\ProductImport\Filament\Livewire;

use Collator;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Lunar\Admin\Support\Concerns\RelationManagers\SearchesCollections;
use Lunar\Models\Collection;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Contracts\Collection as CollectionContract;
use Lunar\Models\ProductType;
use Lunar\ProductImport\Actions\ApplyProfile;
use Lunar\ProductImport\Actions\ChangeHeaderRow;
use Lunar\ProductImport\Actions\ChangeSheetProductType;
use Lunar\ProductImport\Actions\ContinueToStaging;
use Lunar\ProductImport\Actions\DismissProfile;
use Lunar\ProductImport\Actions\IncludeSheet;
use Lunar\ProductImport\Actions\RetrySheetAnalysis;
use Lunar\ProductImport\Actions\SaveProfile;
use Lunar\ProductImport\Actions\SaveSheetMapping;
use Lunar\ProductImport\Actions\SkipSheet;
use Lunar\ProductImport\Data\ColumnFormat;
use Lunar\ProductImport\Data\ColumnMapping;
use Lunar\ProductImport\Data\Grouping;
use Lunar\ProductImport\Data\ImportOptions;
use Lunar\ProductImport\Data\SheetMapping;
use Lunar\ProductImport\Enums\ExistingVariants;
use Lunar\ProductImport\Enums\GroupingStrategy;
use Lunar\ProductImport\Enums\ItemPart;
use Lunar\ProductImport\Enums\MatchKey;
use Lunar\ProductImport\Enums\NewProductStatus;
use Lunar\ProductImport\Enums\SheetAnalysisStatus;
use Lunar\ProductImport\Enums\TargetField;
use Lunar\ProductImport\Enums\ValueMeaning;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\ProductImport\Exceptions\InvalidMapping;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Models\ProductImportSheet;
use Lunar\ProductImport\Reading\HeaderDetector;
use Lunar\ProductImport\Support\LengthUnits;
use Lunar\ProductImport\Support\TargetDefinition;
use Lunar\ProductImport\Support\TargetFieldRegistry;
use Lunar\ProductImport\Support\WeightUnits;

/**
 * The mapping step: a tab per sheet with its columns, targets, number and
 * date formats, list, path, and item separators, conventions (code meanings,
 * item parts, and the image size suffix), confidence, grouping, product
 * type, units, profile offer, and analysis errors, plus the import options.
 * Every change is saved at once and never calls the AI; continuing stages
 * the rows.
 */
class SheetMappingStep extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use SearchesCollections;

    #[Locked]
    public int $importId;

    public ?int $activeSheetId = null;

    /**
     * Sheet id => editable mapping state.
     *
     * @var array<int, array{product_type_id: ?int, grouping: string, weight_unit: ?string, length_unit: ?string, columns: array<int, array{target: ?string, locale: ?string, option: ?string, option_name: ?string, pair: ?int, decimal: ?string, thousands: ?string, date_format: ?string, values: list<array{code: string, meaning: string}>, item_parts: list<string>, image_suffix: ?string, list_separator: ?string, path_separator: ?string, item_separator: ?string}>}>
     */
    public array $sheets = [];

    /**
     * The separators offered for list, category, and packed-items columns.
     *
     * @var list<string>
     */
    public const SEPARATORS = [',', ';', '|', '>', '/', ':', '=', ColumnFormat::NONE];

    /**
     * The date formats offered for date columns.
     *
     * @var list<string>
     */
    public const DATE_FORMATS = ['Y-m-d', 'Y-m-d H:i:s', 'Y-m-d G:i:s', 'd.m.Y', 'd/m/Y', 'm/d/Y', 'Y. m. d.'];

    /**
     * The import options.
     *
     * @var array<string, mixed>
     */
    public array $options = [];

    /**
     * Sheet id => the problem of its mapping, if any.
     *
     * @var array<int, string>
     */
    public array $mappingErrors = [];

    public function mount(ProductImport $import): void
    {
        $this->importId = $import->id;
        $this->loadState();
    }

    public function import(): ProductImport
    {
        return ProductImport::query()->findOrFail($this->importId);
    }

    /**
     * Load the stored mappings and options into the editable state.
     */
    public function loadState(): void
    {
        $import = $this->import();
        $sheets = $import->sheets()->get();

        $this->activeSheetId ??= $sheets->firstWhere('skipped', false)?->id ?? $sheets->first()?->id;

        foreach ($sheets as $sheet) {
            $this->sheets[$sheet->id] = $this->stateFromMapping($sheet->sheetMapping());
        }

        $options = $import->importOptions();

        $this->options = [
            'match_key' => $options->matchKey->value,
            'existing_variants' => $options->existingVariants->value,
            'new_product_status' => $options->newProductStatus->value,
            'collection_group_id' => $options->collectionGroupId,
            'additional_collection_ids' => $options->additionalCollectionIds,
            'link_variant_images' => $options->linkVariantImages,
        ];
    }

    /**
     * The import options that are Filament fields: the additional
     * collections, searched in every collection group, and whether variant
     * rows link their images to their variants.
     */
    public function optionsForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('options')
            ->components([
                Select::make('additional_collection_ids')
                    ->label(__('lunarpanel.product-import::ui.mapping.additional_collections'))
                    ->helperText(__('lunarpanel.product-import::ui.mapping.additional_collections_help'))
                    ->multiple()
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => static::getCollectionSearchResults($search, 50))
                    ->getOptionLabelsUsing(fn (array $values): array => static::withCollectionPathRelations(Collection::query()->whereIn('id', $values))
                        ->get()
                        ->mapWithKeys(fn (CollectionContract $collection): array => [$collection->getKey() => static::getCollectionOptionLabel($collection)])
                        ->all())
                    ->disabled(fn (): bool => $this->import()->status->isBusy())
                    ->live(),
                Toggle::make('link_variant_images')
                    ->label(__('lunarpanel.product-import::ui.mapping.link_variant_images'))
                    ->helperText(__('lunarpanel.product-import::ui.mapping.link_variant_images_help'))
                    ->disabled(fn (): bool => $this->import()->status->isBusy())
                    ->live(),
            ]);
    }

    /**
     * Save a sheet when one of its fields changes.
     */
    public function updatedSheets(mixed $value, string $key): void
    {
        $sheetId = (int) Str::before($key, '.');

        if (Str::endsWith($key, '.product_type_id')) {
            $this->changeProductType($sheetId);

            return;
        }

        $this->saveSheet($sheetId);
    }

    /**
     * Save the options when one changes.
     */
    public function updatedOptions(): void
    {
        $sheetId = $this->activeSheetId ?? array_key_first($this->sheets);

        if ($sheetId !== null) {
            $this->saveSheet($sheetId);
        }
    }

    /**
     * Validate and store a sheet's mapping and the import options.
     */
    public function saveSheet(int $sheetId): bool
    {
        $sheet = $this->sheet($sheetId);

        try {
            app(SaveSheetMapping::class)->handle($sheet, $this->mappingFromState($sheet), $this->importOptions());
        } catch (InvalidMapping $exception) {
            $this->mappingErrors[$sheetId] = $exception->getMessage();

            return false;
        } catch (ImportActionRefused $exception) {
            $this->notifyRefusal($exception);

            return false;
        }

        unset($this->mappingErrors[$sheetId]);

        return true;
    }

    public function selectSheet(int $sheetId): void
    {
        $this->activeSheetId = $sheetId;
    }

    public function ignoreColumn(int $sheetId, int $index): void
    {
        $this->sheets[$sheetId]['columns'][$index]['target'] = TargetField::Ignore->value;
        $this->saveSheet($sheetId);
    }

    public function restoreColumn(int $sheetId, int $index): void
    {
        $column = $this->sheet($sheetId)->sheetMapping()->column($index);
        $suggested = $column?->suggestedTarget;

        $this->sheets[$sheetId]['columns'][$index]['target'] = $suggested !== TargetField::Ignore->value ? $suggested : null;
        $this->saveSheet($sheetId);
    }

    /**
     * Add an empty code row to a column's code meanings. It is saved once it
     * has a code and a meaning.
     */
    public function addMeaning(int $sheetId, int $index): void
    {
        $this->sheets[$sheetId]['columns'][$index]['values'][] = ['code' => '', 'meaning' => ''];
    }

    /**
     * Remove a code row from a column's code meanings.
     */
    public function removeMeaning(int $sheetId, int $index, int $position): void
    {
        unset($this->sheets[$sheetId]['columns'][$index]['values'][$position]);
        $this->sheets[$sheetId]['columns'][$index]['values'] = array_values($this->sheets[$sheetId]['columns'][$index]['values'] ?? []);
        $this->saveSheet($sheetId);
    }

    /**
     * Add a part to a column's item parts, dropped until staff choose otherwise.
     */
    public function addItemPart(int $sheetId, int $index): void
    {
        $this->sheets[$sheetId]['columns'][$index]['item_parts'][] = ItemPart::Skip->value;
        $this->saveSheet($sheetId);
    }

    /**
     * Remove a part from a column's item parts.
     */
    public function removeItemPart(int $sheetId, int $index, int $position): void
    {
        unset($this->sheets[$sheetId]['columns'][$index]['item_parts'][$position]);
        $this->sheets[$sheetId]['columns'][$index]['item_parts'] = array_values($this->sheets[$sheetId]['columns'][$index]['item_parts'] ?? []);
        $this->saveSheet($sheetId);
    }

    public function changeHeaderRow(int $sheetId, int $headerRow): void
    {
        $this->run(fn () => app(ChangeHeaderRow::class)->handle($this->sheet($sheetId), $headerRow));
    }

    public function skipSheet(int $sheetId): void
    {
        $this->run(fn () => app(SkipSheet::class)->handle($this->sheet($sheetId)));
    }

    public function includeSheet(int $sheetId): void
    {
        $this->run(fn () => app(IncludeSheet::class)->handle($this->sheet($sheetId)));
    }

    public function retryAnalysis(int $sheetId): void
    {
        $this->run(fn () => app(RetrySheetAnalysis::class)->handle($this->sheet($sheetId)));
    }

    public function applyProfile(int $sheetId): void
    {
        $this->run(function () use ($sheetId): void {
            $dropped = app(ApplyProfile::class)->handle($this->sheet($sheetId));

            if ($dropped !== []) {
                Notification::make()->warning()
                    ->title(__('lunarpanel.product-import::ui.mapping.profile_dropped', ['targets' => implode(', ', $dropped)]))
                    ->send();
            }
        });
    }

    public function dismissProfile(int $sheetId): void
    {
        $this->run(fn () => app(DismissProfile::class)->handle($this->sheet($sheetId)));
    }

    /**
     * Give a sheet another product type and say which attributes were unmapped.
     */
    public function changeProductType(int $sheetId): void
    {
        $productTypeId = $this->sheets[$sheetId]['product_type_id'] ?? null;

        if ($productTypeId === null) {
            return;
        }

        $sheet = $this->sheet($sheetId);

        // Keep the other edits before the product type changes.
        $this->saveSheet($sheetId);

        try {
            $dropped = app(ChangeSheetProductType::class)->handle($sheet->fresh(), (int) $productTypeId);
        } catch (ImportActionRefused $exception) {
            $this->notifyRefusal($exception);

            return;
        }

        $this->sheets[$sheetId] = $this->stateFromMapping($sheet->fresh()->sheetMapping());

        if ($dropped !== []) {
            Notification::make()->warning()
                ->title(__('lunarpanel.product-import::ui.mapping.type_dropped', ['attributes' => implode(', ', $dropped)]))
                ->send();
        }

        $this->saveSheet($sheetId);
    }

    /**
     * Continue to staging, disabled with the reason while it is blocked.
     */
    public function continueAction(): Action
    {
        return Action::make('continue')
            ->label(__('lunarpanel.product-import::ui.actions.continue'))
            ->disabled(fn (): bool => $this->blocker() !== null)
            ->tooltip(fn (): ?string => $this->blocker())
            ->action(function (): void {
                $this->run(fn () => app(ContinueToStaging::class)->handle($this->import()));
            });
    }

    /**
     * Save the active sheet's mapping as a named profile.
     */
    public function saveProfileAction(): Action
    {
        return Action::make('saveProfile')
            ->label(__('lunarpanel.product-import::ui.mapping.save_profile'))
            ->color('gray')
            ->schema([
                TextInput::make('name')
                    ->label(__('lunarpanel.product-import::ui.profiles.name'))
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (array $data, Action $action): void {
                try {
                    app(SaveProfile::class)->handle($this->sheet((int) $this->activeSheetId), $data['name'], Filament::auth()->user());
                } catch (ImportActionRefused $exception) {
                    $this->notifyRefusal($exception);
                    $action->halt();

                    return;
                }

                Notification::make()->success()->title(__('lunarpanel.product-import::ui.mapping.profile_saved'))->send();
            });
    }

    /**
     * Why continuing is not possible yet, or null.
     */
    public function blocker(): ?string
    {
        $import = $this->import();

        if ($import->status->isBusy()) {
            return __('lunarpanel.product-import::errors.busy');
        }

        foreach ($this->mappingErrors as $sheetId => $error) {
            $sheet = ProductImportSheet::query()->find($sheetId);

            if ($sheet && ! $sheet->skipped) {
                return __('lunarpanel.product-import::errors.sheet_mapping_invalid', ['sheet' => $sheet->name, 'error' => $error]);
            }
        }

        return app(ContinueToStaging::class)->blocker($import);
    }

    public function render(): View
    {
        $import = $this->import();
        $registry = app(TargetFieldRegistry::class);
        $sheets = $import->sheets()->with('offeredProfile')->get();
        $activeSheet = $sheets->firstWhere('id', $this->activeSheetId) ?? $sheets->first();
        $productTypeId = $activeSheet ? $this->editedProductTypeId($activeSheet->id) : null;

        return view('lunarpanel.product-import::livewire.sheet-mapping-step', [
            'import' => $import,
            'tabs' => $sheets->map(fn (ProductImportSheet $sheet): array => $this->tab($sheet, $activeSheet))->all(),
            'activeSheet' => $activeSheet,
            'columns' => $activeSheet && ! $activeSheet->skipped ? $this->columnRows($activeSheet, $productTypeId) : [],
            'targets' => $this->sortedTargets($productTypeId),
            'languages' => $registry->languageCodes(),
            'productOptions' => $registry->options(),
            'productTypes' => ProductType::query()->orderBy('name')->pluck('name', 'id')->all(),
            'collectionGroups' => CollectionGroup::query()->orderBy('name')->pluck('name', 'id')->all(),
            'weightUnits' => app(WeightUnits::class)->codes(),
            'lengthUnits' => app(LengthUnits::class)->codes(),
            'groupings' => array_map(fn (GroupingStrategy $strategy): string => $strategy->value, GroupingStrategy::cases()),
            'itemParts' => array_map(fn (ItemPart $part): string => $part->value, ItemPart::cases()),
            'decimalSeparators' => array_keys(ColumnFormat::DECIMAL_SEPARATORS),
            'thousandsSeparators' => array_keys(ColumnFormat::THOUSANDS_SEPARATORS),
            'dateFormats' => self::DATE_FORMATS,
            'separators' => self::SEPARATORS,
            'busy' => $import->status->isBusy(),
        ]);
    }

    /**
     * The targets of the target dropdown in the alphabetical order of their
     * labels in the panel's language, so an accented label ("Înălțime
     * variantă") sorts with its letter. The registry keeps its own order,
     * which the AI prompt uses.
     *
     * @return list<TargetDefinition>
     */
    protected function sortedTargets(?int $productTypeId): array
    {
        $collator = new Collator(app()->getLocale());

        return collect(app(TargetFieldRegistry::class)->targets($productTypeId))
            ->sort(fn (TargetDefinition $first, TargetDefinition $second): int => (int) $collator->compare($first->label, $second->label))
            ->values()
            ->all();
    }

    /**
     * The first rows of a sheet, for the header row choice.
     *
     * @return array<int, string>
     */
    public function headerRowChoices(ProductImportSheet $sheet): array
    {
        return $sheet->rows()
            ->orderBy('row_number')
            ->limit(HeaderDetector::CANDIDATE_ROWS)
            ->get(['row_number', 'raw'])
            ->mapWithKeys(fn (ProductImportRow $row): array => [
                $row->row_number => $row->row_number.': '.mb_strimwidth(implode(' | ', array_filter($row->raw)), 0, 80, '…'),
            ])
            ->all();
    }

    /**
     * Whether the edited mapping of a sheet maps a weight column.
     */
    public function mapsWeight(int $sheetId): bool
    {
        foreach ($this->sheets[$sheetId]['columns'] ?? [] as $column) {
            if (in_array($column['target'] ?? null, [TargetField::VariantWeight->value, TargetField::VariantWeightImpact->value], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the edited mapping of a sheet maps a length, width, or height column.
     */
    public function mapsDimensions(int $sheetId): bool
    {
        foreach ($this->sheets[$sheetId]['columns'] ?? [] as $column) {
            if (TargetField::tryFrom((string) ($column['target'] ?? ''))?->isDimension()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a sheet waits for its analysis.
     */
    public function isPending(ProductImportSheet $sheet): bool
    {
        return $sheet->analysis_status === SheetAnalysisStatus::Pending;
    }

    /**
     * Whether a sheet's analysis failed with an error to show.
     */
    public function hasFailedAnalysis(ProductImportSheet $sheet): bool
    {
        return $sheet->analysis_status === SheetAnalysisStatus::Failed && filled($sheet->analysis_error);
    }

    /**
     * Whether a saved profile is offered for a sheet.
     */
    public function hasOfferedProfile(ProductImportSheet $sheet): bool
    {
        return $sheet->analysis_status === SheetAnalysisStatus::ProfileOffered && $sheet->offeredProfile !== null;
    }

    /**
     * A sheet's tab, with its row count, or skipped, as the badge.
     *
     * @return array{id: int, name: string, active: bool, badge: string, badgeColor: string}
     */
    protected function tab(ProductImportSheet $sheet, ?ProductImportSheet $activeSheet): array
    {
        return [
            'id' => $sheet->id,
            'name' => $sheet->name,
            'active' => $activeSheet?->id === $sheet->id,
            'badge' => $sheet->skipped
                ? __('lunarpanel.product-import::ui.mapping.skipped')
                : trans_choice('lunarpanel.product-import::ui.mapping.rows', $sheet->row_count, ['count' => $sheet->row_count]),
            'badgeColor' => match (true) {
                $sheet->skipped => 'gray',
                $sheet->analysis_status === SheetAnalysisStatus::Failed => 'danger',
                default => 'primary',
            },
        ];
    }

    /**
     * The rows of the mapping table: each column of a sheet with its profile,
     * its edited target, what that target asks for, and the AI's confidence.
     *
     * @return list<array{index: int, header: string, fill: int, samples: string, target: ?string, takesLocale: bool, isOption: bool, isPair: bool, needsOptionName: bool, isIgnored: bool, suggestion: ?string, confidence: ?int, confidenceColor: string, flagged: bool, format: ?string, formatMissing: bool, separators: array<string, bool>, meanings: list<string>, takesItemParts: bool, takesImageSuffix: bool}>
     */
    protected function columnRows(ProductImportSheet $sheet, ?int $productTypeId): array
    {
        $registry = app(TargetFieldRegistry::class);
        $reviewThreshold = (float) config('lunar.product-import.ai.confidence.review', 0.85);
        $profiles = collect($sheet->columns ?? [])->keyBy('index');
        $rows = [];

        foreach ($sheet->sheetMapping()->columns as $column) {
            $edit = $this->sheets[$sheet->id]['columns'][$column->index] ?? [];
            $target = filled($edit['target'] ?? null) ? $edit['target'] : null;
            $profile = $profiles->get($column->index, []);
            $flagged = $column->flagged && $target === $column->target && $this->formatFromState($edit) == $column->format;
            $format = $target !== null ? $registry->format($target, $productTypeId) : null;
            $edited = $this->formatFromState($edit);
            $field = TargetField::tryFrom((string) $target);

            $rows[] = [
                'index' => $column->index,
                'header' => $column->header,
                'fill' => (int) round(($profile['fill_rate'] ?? 0) * 100),
                'samples' => implode(', ', array_slice($profile['samples'] ?? [], 0, 3)),
                'target' => $target,
                'takesLocale' => $target !== null && $registry->takesLocale($target, $productTypeId),
                'isOption' => $target === TargetField::VariantOption->value,
                'isPair' => (bool) TargetField::tryFrom((string) $target)?->isPair(),
                'needsOptionName' => ($edit['option'] ?? 'new') === 'new',
                'isIgnored' => $target === TargetField::Ignore->value,
                'suggestion' => $target === null && filled($column->suggestedTarget)
                    ? $registry->label($column->suggestedTarget, $productTypeId)
                    : null,
                'confidence' => $column->confidence !== null ? (int) round($column->confidence * 100) : null,
                'confidenceColor' => match (true) {
                    $flagged => 'warning',
                    $column->confidence >= $reviewThreshold => 'success',
                    default => 'gray',
                },
                'flagged' => $flagged,
                'format' => $format,
                'formatMissing' => match ($format) {
                    TargetDefinition::FORMAT_NUMBER => ! $edited?->hasNumberFormat(),
                    TargetDefinition::FORMAT_DATE => ! $edited?->hasDateFormat(),
                    default => false,
                },
                'separators' => collect($field?->separators(($edited?->itemParts ?? []) !== []) ?? [])
                    ->mapWithKeys(fn (string $kind): array => [$kind => ! ColumnFormat::isValidSeparator($edited?->separatorOf($kind))])
                    ->all(),
                'meanings' => array_map(fn (ValueMeaning $meaning): string => $meaning->value, ValueMeaning::for($field)),
                'takesItemParts' => (bool) $field?->takesItemParts(),
                'takesImageSuffix' => (bool) $field?->takesImageSuffix(),
            ];
        }

        return $rows;
    }

    /**
     * The product type chosen for a sheet in the editable state.
     */
    protected function editedProductTypeId(int $sheetId): ?int
    {
        $productTypeId = $this->sheets[$sheetId]['product_type_id'] ?? null;

        return filled($productTypeId) ? (int) $productTypeId : null;
    }

    /**
     * The editable state of a mapping.
     *
     * @return array{product_type_id: ?int, grouping: string, weight_unit: ?string, length_unit: ?string, columns: array<int, array{target: ?string, locale: ?string, option: ?string, option_name: ?string, pair: ?int, decimal: ?string, thousands: ?string, date_format: ?string, values: list<array{code: string, meaning: string}>, item_parts: list<string>, image_suffix: ?string, list_separator: ?string, path_separator: ?string, item_separator: ?string}>}
     */
    protected function stateFromMapping(SheetMapping $mapping): array
    {
        $columns = [];

        foreach ($mapping->columns as $column) {
            $columns[$column->index] = [
                'target' => $column->target,
                'locale' => $column->locale,
                'option' => $column->optionId !== null ? (string) $column->optionId : ($column->is(TargetField::VariantOption) ? 'new' : null),
                'option_name' => $column->optionName,
                'pair' => $column->pair,
                'decimal' => $column->format?->decimalName(),
                'thousands' => $column->format?->thousandsName(),
                'date_format' => $column->format?->dateFormat,
                'values' => array_map(
                    fn (string|int $code, string $meaning): array => ['code' => (string) $code, 'meaning' => $meaning],
                    array_keys($column->format?->values ?? []),
                    array_values($column->format?->values ?? []),
                ),
                'item_parts' => $column->format?->itemParts ?? [],
                'image_suffix' => $column->format?->imageSuffix,
                'list_separator' => $column->format?->listSeparator,
                'path_separator' => $column->format?->pathSeparator,
                'item_separator' => $column->format?->itemSeparator,
            ];
        }

        return [
            'product_type_id' => $mapping->productTypeId ?? $this->defaultProductTypeId(),
            'grouping' => $mapping->grouping->strategy->value,
            'weight_unit' => $mapping->weightUnit,
            'length_unit' => $mapping->lengthUnit,
            'columns' => $columns,
        ];
    }

    /**
     * The product type a sheet without a mapping starts with, such as one
     * whose analysis failed: the first one, as the AI analysis uses, so the
     * target list offers its attributes (short description, SEO fields)
     * before staff choose a type. It is stored with the first saved change.
     */
    protected function defaultProductTypeId(): ?int
    {
        return ProductType::query()->orderBy('id')->value('id');
    }

    /**
     * The mapping of a sheet's editable state, keeping the AI's confidence.
     */
    protected function mappingFromState(ProductImportSheet $sheet): SheetMapping
    {
        $stored = $sheet->sheetMapping();
        $state = $this->sheets[$sheet->id] ?? $this->stateFromMapping($stored);
        $columns = [];
        $productTypeId = filled($state['product_type_id'] ?? null) ? (int) $state['product_type_id'] : null;

        foreach ($stored->columns as $column) {
            $edit = $state['columns'][$column->index] ?? [];
            $target = filled($edit['target'] ?? null) ? $edit['target'] : null;
            $option = $edit['option'] ?? null;
            $isOption = $target === TargetField::VariantOption->value;

            $format = array_key_exists('decimal', $edit) || array_key_exists('date_format', $edit)
                ? $this->formatFromState($edit)
                : $column->format;

            $columns[] = new ColumnMapping(
                index: $column->index,
                header: $column->header,
                target: $target,
                locale: $this->localeFor($target, $edit['locale'] ?? null, $productTypeId),
                optionId: $isOption && is_numeric($option) ? (int) $option : null,
                optionName: $isOption && ! is_numeric($option) ? ($edit['option_name'] ?? null) : null,
                confidence: $column->confidence,
                suggestedTarget: $column->suggestedTarget,
                flagged: $column->flagged && $target === $column->target && $format == $column->format,
                format: $format,
                pair: TargetField::tryFrom((string) $target)?->isPair() && is_numeric($edit['pair'] ?? null) ? max(1, (int) $edit['pair']) : null,
            );
        }

        $strategy = GroupingStrategy::tryFrom((string) ($state['grouping'] ?? '')) ?? GroupingStrategy::PerRow;

        return new SheetMapping(
            headerRow: $sheet->header_row,
            productTypeId: $productTypeId,
            grouping: new Grouping($strategy, null, $stored->grouping->confidence),
            columns: $columns,
            weightUnit: filled($state['weight_unit'] ?? null) ? (string) $state['weight_unit'] : null,
            lengthUnit: filled($state['length_unit'] ?? null) ? (string) $state['length_unit'] : null,
        );
    }

    /**
     * The column format of a column's editable state: its number separators
     * by name, its date format, its code meanings (rows without a code or a
     * meaning are left out), its item parts, its image size suffix, and its
     * list, path, and item separators (an unusable one is left out). Null
     * when it has none of them.
     *
     * @param  array<string, mixed>  $edit
     */
    protected function formatFromState(array $edit): ?ColumnFormat
    {
        $decimal = ColumnFormat::DECIMAL_SEPARATORS[(string) ($edit['decimal'] ?? '')] ?? null;
        $thousands = ColumnFormat::THOUSANDS_SEPARATORS[(string) ($edit['thousands'] ?? '')] ?? null;
        $dateFormat = filled($edit['date_format'] ?? null) ? Str::trim((string) $edit['date_format']) : null;
        $meanings = [];

        foreach ((array) ($edit['values'] ?? []) as $row) {
            if (is_array($row) && filled($row['code'] ?? null) && filled($row['meaning'] ?? null)) {
                $meanings[Str::trim((string) $row['code'])] = (string) $row['meaning'];
            }
        }

        $format = (new ColumnFormat(
            $decimal,
            $thousands,
            $dateFormat,
            itemParts: array_values(array_filter(array_map('strval', (array) ($edit['item_parts'] ?? [])), fn (string $part): bool => $part !== '')),
            imageSuffix: filled($edit['image_suffix'] ?? null) ? Str::trim((string) $edit['image_suffix']) : null,
            listSeparator: ColumnFormat::separator($edit['list_separator'] ?? null),
            pathSeparator: ColumnFormat::separator($edit['path_separator'] ?? null),
            itemSeparator: ColumnFormat::separator($edit['item_separator'] ?? null),
        ))->withMeanings($meanings);

        return $format->isEmpty() ? null : $format;
    }

    /**
     * The chosen language of a target that takes one, else the default language.
     */
    protected function localeFor(?string $target, ?string $chosen, ?int $productTypeId): ?string
    {
        $registry = app(TargetFieldRegistry::class);

        if ($target === null || ! $registry->takesLocale($target, $productTypeId)) {
            return null;
        }

        return filled($chosen) ? $chosen : $registry->defaultLanguage();
    }

    /**
     * The import options of the editable state.
     */
    protected function importOptions(): ImportOptions
    {
        return new ImportOptions(
            matchKey: MatchKey::tryFrom((string) ($this->options['match_key'] ?? '')) ?? MatchKey::Sku,
            existingVariants: ExistingVariants::tryFrom((string) ($this->options['existing_variants'] ?? '')) ?? ExistingVariants::Update,
            newProductStatus: NewProductStatus::tryFrom((string) ($this->options['new_product_status'] ?? '')) ?? NewProductStatus::Draft,
            collectionGroupId: filled($this->options['collection_group_id'] ?? null) ? (int) $this->options['collection_group_id'] : null,
            additionalCollectionIds: array_values(array_unique(array_map('intval', array_filter((array) ($this->options['additional_collection_ids'] ?? []), 'is_numeric')))),
            linkVariantImages: (bool) ($this->options['link_variant_images'] ?? false),
        );
    }

    /**
     * A sheet of this import.
     */
    protected function sheet(int $sheetId): ProductImportSheet
    {
        return ProductImportSheet::query()->where('product_import_id', $this->importId)->findOrFail($sheetId);
    }

    /**
     * Run an action, show a refusal, reload the state, and tell the page.
     */
    protected function run(callable $action): void
    {
        try {
            $action();
        } catch (ImportActionRefused $exception) {
            $this->notifyRefusal($exception);
        }

        $this->loadState();
        $this->dispatch('product-import-changed');
    }

    protected function notifyRefusal(ImportActionRefused $exception): void
    {
        Notification::make()->danger()->title($exception->getMessage())->send();
    }
}
