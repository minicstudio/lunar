<?php

namespace Lunar\ProductImport\Validation;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lunar\Models\Currency;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Lunar\ProductImport\Data\ColumnFormat;
use Lunar\ProductImport\Data\ImportOptions;
use Lunar\ProductImport\Data\NormalizedRow;
use Lunar\ProductImport\Data\SheetMapping;
use Lunar\ProductImport\Enums\ExistingVariants;
use Lunar\ProductImport\Enums\MatchKey;
use Lunar\ProductImport\Enums\NewProductStatus;
use Lunar\ProductImport\Enums\RowAction;
use Lunar\ProductImport\Enums\RowSkipReason;
use Lunar\ProductImport\Enums\RowStatus;
use Lunar\ProductImport\Enums\TargetField;
use Lunar\ProductImport\Enums\ValueAction;
use Lunar\ProductImport\Enums\ValueType;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Models\ProductImportSheet;
use Lunar\ProductImport\Normalizing\CategoryList;
use Lunar\ProductImport\Normalizing\NumberParser;
use Lunar\ProductImport\Normalizing\RowDiscount;
use Lunar\ProductImport\Persisting\ProductUrls;
use Lunar\ProductImport\Structuring\ParentResolver;
use Lunar\ProductImport\Support\TargetDefinition;
use Lunar\ProductImport\Support\TargetFieldRegistry;
use Lunar\ProductImport\Taxonomy\ValueKeys;

/**
 * Applies the validation rules to staged rows: required values, number
 * checks, duplicate match keys across sheets, option combinations inside a
 * product group, matching existing variants (also of trashed products),
 * skip-existing, value decisions, URL slugs, and the non-blocking warnings
 * (also for a status value that is not recognized when new products take
 * their status from the status column).
 */
class ImportValidator
{
    /**
     * Rows read per chunk.
     */
    protected const CHUNK = 500;

    /**
     * Valid EAN lengths.
     *
     * @var list<int>
     */
    protected const EAN_LENGTHS = [8, 12, 13, 14];

    public function __construct(
        protected TargetFieldRegistry $registry,
        protected NumberParser $numbers,
        protected ProductUrls $urls,
    ) {}

    /**
     * Validate every included data row of the import.
     */
    public function validate(ProductImport $import): void
    {
        $this->run($import, null);
    }

    /**
     * Validate only the rows of the given product groups.
     *
     * @param  list<string>  $groupKeys
     */
    public function validateGroups(ProductImport $import, array $groupKeys): void
    {
        if ($groupKeys !== []) {
            $this->run($import, array_values(array_unique($groupKeys)));
        }
    }

    /**
     * Validate the included rows, or only those of the given groups.
     *
     * @param  ?list<string>  $groupKeys
     */
    protected function run(ProductImport $import, ?array $groupKeys): void
    {
        $context = $this->context($import);

        $rows = fn (): Builder => ProductImportRow::includedIn($import)
            ->when($groupKeys !== null, fn (Builder $query) => $query->whereIn('group_key', $groupKeys));

        $this->storeMatchValues($rows(), $context['options']->matchKey);

        $values = $rows()->whereNotNull('match_value')->distinct()->pluck('match_value')->all();

        $context['existing'] = $this->existingVariants($values, $context['options']->matchKey);
        $context['duplicates'] = $this->duplicates($import, $values);

        $group = [];
        $groupKey = null;

        foreach ($rows()->orderBy('group_key')->orderBy('product_import_sheet_id')->orderBy('row_number')->lazy(self::CHUNK) as $row) {
            if ($groupKey !== null && $row->group_key !== $groupKey) {
                $this->validateGroup($group, $context);
                $group = [];
            }

            $groupKey = $row->group_key;
            $group[] = $row;
        }

        if ($group !== []) {
            $this->validateGroup($group, $context);
        }
    }

    /**
     * What every group needs: options, sheets and their mappings, the default
     * language, and the value decisions.
     *
     * @return array<string, mixed>
     */
    protected function context(ProductImport $import): array
    {
        $decisions = [];

        foreach ($import->values()->get(['type', 'option_key', 'normalized_key', 'action']) as $value) {
            $decisions[$value->type->value][$value->option_key][$value->normalized_key] = $value->action;
        }

        return [
            'options' => $import->importOptions(),
            'sheets' => $import->sheets()->get()->keyBy('id')->map(fn (ProductImportSheet $sheet): array => [
                'name' => $sheet->name,
                'mapping' => $sheet->sheetMapping(),
            ]),
            'locale' => $this->registry->defaultLanguage(),
            'factor' => (int) (Currency::getDefault()?->factor ?? 100),
            'decisions' => $decisions,
            'references' => $this->references($import),
            'slugs' => $this->slugPlan($import),
        ];
    }

    /**
     * Which product groups use each URL slug, by language and slug, in the
     * order the groups are written, filled only when a sheet maps a slug
     * column. A group that comes earlier takes the slug first.
     *
     * @return array<string, array<string, list<string>>>
     */
    protected function slugPlan(ProductImport $import): array
    {
        $maps = $import->sheets()->where('skipped', false)->get()->contains(
            fn (ProductImportSheet $sheet): bool => $sheet->sheetMapping()->maps(TargetField::ProductSlug),
        );

        if (! $maps) {
            return [];
        }

        $plan = [];
        $group = [];
        $groupKey = null;

        $flush = function () use (&$plan, &$group, &$groupKey): void {
            foreach ($this->groupSlugs($group) as $locale => $slug) {
                $plan[$locale][$slug['slug']][] = (string) $groupKey;
            }

            $group = [];
        };

        $rows = ProductImportRow::includedIn($import)
            ->where(fn (Builder $query) => $query->whereNull('skip_reason')->orWhere('skip_reason', '!=', RowSkipReason::Staff->value))
            ->select(['id', 'group_key', 'role', 'normalized', 'overrides'])
            ->orderBy('group_key')
            ->orderBy('product_import_sheet_id')
            ->orderBy('row_number');

        foreach ($rows->lazy(self::CHUNK) as $row) {
            if ($groupKey !== null && $row->group_key !== $groupKey) {
                $flush();
            }

            $groupKey = $row->group_key;
            $group[$row->id] = [$row, $row->effectiveRow()];
        }

        $flush();

        return $plan;
    }

    /**
     * A group's URL slug in each language, the first one among its rows with
     * the product row's first, and the rows that hold it.
     *
     * @param  array<int, array{0: ProductImportRow, 1: NormalizedRow}>  $group  Row id => row and effective values.
     * @return array<string, array{slug: string, rows: list<int>}>
     */
    protected function groupSlugs(array $group): array
    {
        uasort($group, fn (array $a, array $b): int => (int) ! ($a[0]->role?->isProduct() ?? false) <=> (int) ! ($b[0]->role?->isProduct() ?? false));

        $slugs = [];

        foreach ($group as $id => [$row, $values]) {
            foreach ($values->values as $field => $value) {
                if (! filled($value) || ! Str::startsWith($field, TargetField::ProductSlug->value.'.')) {
                    continue;
                }

                $locale = Str::after($field, TargetField::ProductSlug->value.'.');
                $slugs[$locale] ??= ['slug' => (string) $value, 'rows' => []];

                if ($slugs[$locale]['slug'] === $value) {
                    $slugs[$locale]['rows'][] = $id;
                }
            }
        }

        return $slugs;
    }

    /**
     * A resolver for up-sell and cross-sell references over the import's
     * rows, filled only when a sheet maps such a column.
     */
    protected function references(ProductImport $import): ParentResolver
    {
        $resolver = new ParentResolver;

        $maps = $import->sheets()->where('skipped', false)->get()->contains(
            fn (ProductImportSheet $sheet): bool => $sheet->sheetMapping()->maps(TargetField::ProductUpsells) || $sheet->sheetMapping()->maps(TargetField::ProductCrossSells),
        );

        if (! $maps) {
            return $resolver;
        }

        $references = [];

        ProductImportRow::includedIn($import)->select(['id', 'source_id', 'normalized', 'overrides'])->chunkById(self::CHUNK, function (Collection $chunk) use ($resolver, &$references): void {
            foreach ($chunk as $row) {
                $values = $row->effectiveRow();
                $resolver->addRow($row->id, $row->source_id, filled($values->get(TargetField::VariantSku->value)) ? (string) $values->get(TargetField::VariantSku->value) : null);

                foreach ([TargetField::ProductUpsells, TargetField::ProductCrossSells] as $field) {
                    array_push($references, ...array_map('strval', (array) $values->get($field->value)));
                }
            }
        });

        $resolver->preloadExisting($references);

        return $resolver;
    }

    /**
     * Store each row's effective match key value, so duplicates can be found
     * with one grouped query.
     *
     * @param  Builder<ProductImportRow>  $rows
     */
    protected function storeMatchValues(Builder $rows, MatchKey $matchKey): void
    {
        $field = $matchKey->target()->value;

        $rows->select(['id', 'normalized', 'overrides', 'match_value', 'role'])->chunkById(self::CHUNK, function (Collection $chunk) use ($field): void {
            DB::transaction(function () use ($chunk, $field): void {
                foreach ($chunk as $row) {
                    /** @var ProductImportRow $row */
                    $value = $row->isVariantRow() ? $row->effectiveRow()->get($field) : null;
                    $value = filled($value) ? Str::limit(Str::trim((string) $value), 250, '') : null;

                    if ($value !== $row->match_value) {
                        ProductImportRow::query()->whereKey($row->id)->update(['match_value' => $value]);
                    }
                }
            });
        });
    }

    /**
     * Existing variants by match key value, with their product and whether it
     * is in the trash. Loaded in chunks.
     *
     * @param  list<string>  $values
     * @return array<string, array{variant_id: int, product_id: int, trashed: bool}>
     */
    protected function existingVariants(array $values, MatchKey $matchKey): array
    {
        $column = $matchKey->column();
        $existing = [];

        foreach (array_chunk($values, self::CHUNK) as $chunk) {
            $variants = ProductVariant::withTrashed()
                ->whereIn($column, $chunk)
                ->orderByRaw('case when deleted_at is null then 0 else 1 end')
                ->orderBy('id')
                ->get(['id', 'product_id', $column, 'deleted_at']);

            $products = Product::withTrashed()
                ->whereIn('id', $variants->pluck('product_id')->unique())
                ->get(['id', 'deleted_at'])
                ->keyBy('id');

            foreach ($variants as $variant) {
                $product = $products->get($variant->product_id);
                $key = (string) $variant->{$column};

                if ($product === null || isset($existing[$key])) {
                    continue;
                }

                $existing[$key] = [
                    'variant_id' => (int) $variant->id,
                    'product_id' => (int) $variant->product_id,
                    'trashed' => $product->deleted_at !== null || $variant->deleted_at !== null,
                ];
            }
        }

        return $existing;
    }

    /**
     * The rows sharing each match key value, found with one grouped query
     * over the import. Rows staff skipped do not count.
     *
     * @param  list<string>  $values
     * @return array<string, list<array{id: int, sheet: int, row: int}>>
     */
    protected function duplicates(ProductImport $import, array $values): array
    {
        if ($values === []) {
            return [];
        }

        $active = fn (): Builder => ProductImportRow::includedIn($import)
            ->where(fn (Builder $query) => $query->whereNull('skip_reason')->orWhere('skip_reason', '!=', RowSkipReason::Staff->value));

        $repeated = [];

        foreach (array_chunk($values, self::CHUNK) as $chunk) {
            array_push($repeated, ...$active()
                ->whereIn('match_value', $chunk)
                ->groupBy('match_value')
                ->havingRaw('count(*) > 1')
                ->pluck('match_value')
                ->all());
        }

        $duplicates = [];

        foreach (array_chunk($repeated, self::CHUNK) as $chunk) {
            foreach ($active()->whereIn('match_value', $chunk)->orderBy('product_import_sheet_id')->orderBy('row_number')->get(['id', 'product_import_sheet_id', 'row_number', 'match_value']) as $row) {
                $duplicates[$row->match_value][] = ['id' => $row->id, 'sheet' => $row->product_import_sheet_id, 'row' => $row->row_number];
            }
        }

        return $duplicates;
    }

    /**
     * Validate the rows of one product group and store the results. A group
     * may hold rows of several sheets when rows are grouped by parent, so
     * each row is read with its own sheet's mapping.
     *
     * @param  list<ProductImportRow>  $rows
     * @param  array<string, mixed>  $context
     */
    protected function validateGroup(array $rows, array $context): void
    {
        /** @var ImportOptions $options */
        $options = $context['options'];
        $locale = (string) $context['locale'];
        $matchField = $options->matchKey->target()->value;
        $mappingOf = fn (ProductImportRow $row): SheetMapping => $context['sheets'][$row->product_import_sheet_id]['mapping'];

        $effective = [];
        $messages = [];
        $matches = [];

        foreach ($rows as $row) {
            $effective[$row->id] = $row->effectiveRow();
            $messages[$row->id] = [];
            $matches[$row->id] = $row->isVariantRow() && $row->match_value !== null ? ($context['existing'][$row->match_value] ?? null) : null;
        }

        $typeSkipped = fn (ProductImportRow $row): bool => $effective[$row->id]->note('skip') !== null;
        $active = array_values(array_filter($rows, fn (ProductImportRow $row): bool => $row->skip_reason !== RowSkipReason::Staff && ! $typeSkipped($row)));
        $activeVariants = array_values(array_filter($active, fn (ProductImportRow $row): bool => $row->isVariantRow()));

        $productIds = array_values(array_unique(array_filter(array_map(fn (?array $match): ?int => $match['product_id'] ?? null, $matches))));
        $joinedProductId = Str::startsWith((string) $rows[0]->group_key, 'product:') ? (int) Str::after((string) $rows[0]->group_key, 'product:') : null;

        if ($joinedProductId !== null && ! in_array($joinedProductId, $productIds, true)) {
            $productIds[] = $joinedProductId;
        }

        $groupProductId = count($productIds) === 1 ? $productIds[0] : null;
        $trashedProduct = $groupProductId !== null && collect($matches)->contains(fn (?array $match): bool => ($match['trashed'] ?? false) && $match['product_id'] === $groupProductId);

        if (count($productIds) > 1) {
            foreach ($rows as $row) {
                $messages[$row->id][] = $this->error('several_products', null, ['count' => count($productIds)]);
            }
        }

        if ($productIds === [] && ! $this->groupHas($effective, TargetField::ProductName->value.'.'.$locale)) {
            foreach ($rows as $row) {
                $messages[$row->id][] = $this->error('missing_name', TargetField::ProductName->value, ['locale' => $locale]);
            }
        }

        $this->checkOptions($activeVariants, $effective, $mappingOf, $messages, $context['decisions']);
        $this->checkSlugs($rows, $effective, $groupProductId, $messages, $context);

        $categorySkipped = $this->categorySkipped($effective, $context['decisions']);
        $brandSkipped = $this->brandSkipped($effective, $context['decisions']);
        $hasCategory = $this->groupHas($effective, TargetField::ProductCategory->value);
        $mapsCategory = collect($rows)->contains(fn (ProductImportRow $row): bool => $mappingOf($row)->maps(TargetField::ProductCategory));

        foreach ($rows as $row) {
            $values = $effective[$row->id];
            $mapping = $mappingOf($row);
            $match = $matches[$row->id];
            $isVariant = $row->isVariantRow();
            $action = $match !== null || ($groupProductId !== null && ! $isVariant) ? RowAction::Update : RowAction::Create;

            if ($isVariant && ! $values->filled($matchField)) {
                $messages[$row->id][] = $this->error('missing', $matchField, ['field' => $this->fieldLabel($matchField)]);
            }

            $this->checkNumbers($values, $mapping, (int) $context['factor'], $messages[$row->id]);
            $this->checkDiscount($values, $messages[$row->id]);

            if ($isVariant && $match === null) {
                if ($matchField !== TargetField::VariantSku->value && ! $values->filled(TargetField::VariantSku->value)) {
                    $messages[$row->id][] = $this->error('missing', TargetField::VariantSku->value, ['field' => $this->fieldLabel(TargetField::VariantSku->value)]);
                }

                if (! $values->filled(TargetField::VariantPrice->value) && ! isset($values->invalid[TargetField::VariantPrice->value])) {
                    $messages[$row->id][] = $this->error('missing', TargetField::VariantPrice->value, ['field' => $this->fieldLabel(TargetField::VariantPrice->value)]);
                }
            }

            if (! $isVariant && ! $typeSkipped($row) && $activeVariants === [] && $groupProductId === null) {
                $messages[$row->id][] = $this->error('no_variants');
            }

            if (($reference = $values->note('unresolved_parent')) !== null) {
                $messages[$row->id][] = $this->error('parent_not_found', TargetField::SourceParent->value, ['reference' => $reference]);
            }

            foreach ((array) $values->note('missing_option_values') as $option) {
                $messages[$row->id][] = $this->error('missing_option_value', TargetField::VariantOptionValue->value, ['option' => $option]);
            }

            if ($isVariant && $row->skip_reason !== RowSkipReason::Staff && $row->match_value !== null && count($context['duplicates'][$row->match_value] ?? []) > 1) {
                $messages[$row->id][] = $this->duplicateError($row, $matchField, $context);
            }

            $this->addWarnings($row, $values, $action, $mapping, $effective, $messages[$row->id], $context);

            if ($productIds === [] && $options->newProductStatus === NewProductStatus::FromColumn && isset($values->invalid[TargetField::ProductStatus->value])) {
                $messages[$row->id][] = $this->warning('unknown_status', TargetField::ProductStatus->value, ['value' => $values->invalid[TargetField::ProductStatus->value]]);
            }

            if ($mapsCategory && $mapping->maps(TargetField::ProductCategory) && (! $hasCategory || $categorySkipped)) {
                $messages[$row->id][] = $this->warning('no_category', TargetField::ProductCategory->value);
            }

            if ($brandSkipped) {
                $messages[$row->id][] = $this->warning('no_brand', TargetField::ProductBrand->value);
            }

            if ($trashedProduct) {
                $messages[$row->id][] = $this->info('product_restored', null, ['id' => $groupProductId]);
            }

            $skipReason = $row->skip_reason === RowSkipReason::Staff ? RowSkipReason::Staff : null;

            if ($skipReason === null && $typeSkipped($row)) {
                $skipReason = RowSkipReason::RowType;
                $messages[$row->id] = [$this->info('row_type_skipped', null, ['type' => (string) $values->note('skip')])];
            }

            if ($skipReason === null && $match !== null && $options->existingVariants === ExistingVariants::Skip) {
                $skipReason = RowSkipReason::Existing;
                $messages[$row->id][] = $this->info('skip_existing');
            }

            $skippedOption = $isVariant ? $this->skippedOptionValue($values, $mapping, $context['decisions']) : null;

            if ($skipReason === null && $skippedOption !== null) {
                $skipReason = RowSkipReason::OptionValue;
                $messages[$row->id][] = $this->info('option_value_skipped', null, ['value' => $skippedOption]);
            }

            $levels = array_column($messages[$row->id], 'level');

            $status = match (true) {
                $skipReason !== null => RowStatus::Skipped,
                in_array('error', $levels, true) => RowStatus::Error,
                in_array('warning', $levels, true) => RowStatus::Warning,
                default => RowStatus::Valid,
            };

            ProductImportRow::query()->whereKey($row->id)->update([
                'status' => $status->value,
                'skip_reason' => $skipReason?->value,
                'messages' => json_encode($messages[$row->id], JSON_UNESCAPED_UNICODE),
                'action' => $action->value,
                'matched_product_id' => $match['product_id'] ?? $groupProductId,
                'matched_variant_id' => $match['variant_id'] ?? null,
            ]);
        }
    }

    /**
     * Repeated option combinations, variant rows without an option value, and
     * groups of several variant rows without any option. Options come from
     * option columns and from option names in cells.
     *
     * @param  list<ProductImportRow>  $rows  The group's active variant rows.
     * @param  array<int, NormalizedRow>  $effective
     * @param  callable(ProductImportRow): SheetMapping  $mappingOf
     * @param  array<int, list<array<string, mixed>>>  $messages
     * @param  array<string, array<string, array<string, ?ValueAction>>>  $decisions
     */
    protected function checkOptions(array $rows, array $effective, callable $mappingOf, array &$messages, array $decisions = []): void
    {
        if (count($rows) < 2) {
            return;
        }

        $parts = [];
        $hasOptionSource = false;

        foreach ($rows as $row) {
            $mapping = $mappingOf($row);
            $options = [];

            foreach ($mapping->optionKeys() as $key) {
                $hasOptionSource = true;
                $value = (string) $effective[$row->id]->get('variant.option.'.$key);

                if ($value !== '') {
                    $options[$key] = $value;
                }
            }

            if ($mapping->pairs() !== [] || $mapping->maps(TargetField::VariantOptions)) {
                $hasOptionSource = true;
            }

            foreach ($effective[$row->id]->namedOptions() as $key => $value) {
                // A skipped option name imports the variant without that option.
                if (($decisions[ValueType::Option->value][''][Str::after($key, 'name:')] ?? null) !== ValueAction::Skip) {
                    $options[$key] = $value;
                }
            }

            $parts[$row->id] = $options;
        }

        if (! $hasOptionSource) {
            foreach ($rows as $row) {
                $messages[$row->id][] = $this->error('no_option_column', TargetField::VariantOption->value);
            }

            return;
        }

        $keys = array_values(array_unique(array_merge(...array_values(array_map('array_keys', $parts)))));
        $combinations = [];

        foreach ($rows as $row) {
            if ($parts[$row->id] === []) {
                $messages[$row->id][] = $this->error('missing_option', TargetField::VariantOption->value);

                continue;
            }

            $values = array_map(fn (string $key): string => $parts[$row->id][$key] ?? '', $keys);

            $combinations[Str::lower(implode("\0", $values))][] = [$row->id, implode(' / ', array_filter($values, fn (string $part): bool => $part !== ''))];
        }

        foreach ($combinations as $entries) {
            if (count($entries) < 2) {
                continue;
            }

            foreach ($entries as [$rowId, $label]) {
                $messages[$rowId][] = $this->error('repeated_options', TargetField::VariantOption->value, ['combination' => $label]);
            }
        }
    }

    /**
     * URL slugs: a cell that gives no slug; for an existing product that has
     * a URL in the slug's language, the URL it keeps; otherwise a slug that
     * another product, or a group of the import that is written earlier,
     * already uses, with the suffixed slug that is used instead.
     *
     * @param  list<ProductImportRow>  $rows
     * @param  array<int, NormalizedRow>  $effective
     * @param  array<int, list<array<string, mixed>>>  $messages
     * @param  array<string, mixed>  $context
     */
    protected function checkSlugs(array $rows, array $effective, ?int $productId, array &$messages, array $context): void
    {
        $group = [];

        foreach ($rows as $row) {
            $group[$row->id] = [$row, $effective[$row->id]];

            foreach ($effective[$row->id]->invalid as $field => $raw) {
                if (Str::startsWith($field, TargetField::ProductSlug->value.'.')) {
                    $messages[$row->id][] = $this->warning('slug_empty', $field, ['field' => $this->fieldLabel($field), 'value' => $raw]);
                }
            }
        }

        foreach ($this->groupSlugs($group) as $locale => ['slug' => $slug, 'rows' => $rowIds]) {
            $languageId = $this->urls->languageId($locale);

            if ($languageId === null) {
                continue;
            }

            $field = TargetField::ProductSlug->value.'.'.$locale;
            $own = $productId !== null ? $this->urls->slugsOf($productId, $languageId) : [];

            if ($own !== []) {
                if (! in_array($slug, $own, true)) {
                    foreach ($rowIds as $rowId) {
                        $messages[$rowId][] = $this->warning('slug_kept', $field, ['url' => $own[0], 'slug' => $slug]);
                    }
                }

                continue;
            }

            $taken = $this->urls->taken($slug, $languageId, $productId);

            foreach ($context['slugs'][$locale][$slug] ?? [] as $groupKey) {
                if ($groupKey === (string) $rows[0]->group_key) {
                    break;
                }

                $taken[] = ProductUrls::suffixed($slug, $taken);
            }

            $used = ProductUrls::suffixed($slug, $taken);

            if ($used !== $slug) {
                foreach ($rowIds as $rowId) {
                    $messages[$rowId][] = $this->warning('slug_taken', $field, ['slug' => $slug, 'used' => $used]);
                }
            }
        }
    }

    /**
     * Numbers and dates that do not read in their column's format, fractional
     * whole numbers, and negative numbers where they are not allowed.
     *
     * @param  list<array<string, mixed>>  $messages
     */
    protected function checkNumbers(NormalizedRow $values, SheetMapping $mapping, int $factor, array &$messages): void
    {
        foreach ($values->invalid as $field => $raw) {
            // An unknown status and a cell without a slug are warnings, see
            // validateGroup() and checkSlugs().
            if ($field === TargetField::ProductStatus->value || Str::startsWith($field, TargetField::ProductSlug->value.'.')) {
                continue;
            }

            $column = $mapping->columnForField($field);
            $format = $column?->format ?? new ColumnFormat;
            $label = $this->fieldLabel($field);

            $kind = $column !== null ? $this->registry->format((string) $column->target, $mapping->productTypeId) : null;

            if ($kind === TargetDefinition::FORMAT_DATE) {
                $messages[] = $this->error('not_date', $field, ['field' => $label, 'value' => $raw, 'format' => (string) $format->dateFormat]);

                continue;
            }

            if ($kind === null) {
                $messages[] = $this->error('not_recognized', $field, ['field' => $label, 'value' => $raw]);

                continue;
            }

            $code = match (true) {
                ! (bool) TargetField::tryFrom($field)?->isWholeNumber() => 'not_in_format',
                $this->numbers->parse($raw, $format) !== null => 'not_whole',
                default => 'not_whole_in_format',
            };

            $messages[] = $this->error($code, $field, ['field' => $label, 'value' => $raw, 'format' => $format->describeNumber()]);
        }

        foreach ($values->values as $field => $value) {
            $target = TargetField::tryFrom($field);

            if ((! is_int($value) && ! is_float($value)) || $value >= 0 || ! $target?->isNumber() || $target->allowsNegative()) {
                continue;
            }

            $display = $target->isMoney() ? (string) ($value / max(1, $factor)) : (string) $value;
            $messages[] = $this->error('negative', $field, ['field' => $this->fieldLabel($field), 'value' => $display]);
        }
    }

    /**
     * The non-blocking warnings: stock of 0, an EAN of an unusual length, a
     * product stock that is not split among the variants, a discount that
     * ended or that the shop does not apply, a sale price that is not
     * imported, up-sells and cross-sells that name nothing, images that are
     * not web addresses, and empty mapped cells on an update.
     *
     * @param  array<int, NormalizedRow>  $group
     * @param  list<array<string, mixed>>  $messages
     * @param  array<string, mixed>  $context
     */
    protected function addWarnings(ProductImportRow $row, NormalizedRow $values, RowAction $action, SheetMapping $mapping, array $group, array &$messages, array $context): void
    {
        $isVariant = $row->isVariantRow();

        if ($isVariant && $values->get(TargetField::VariantStock->value) === 0) {
            $messages[] = $this->warning('stock_zero', TargetField::VariantStock->value);
        }

        $ean = $values->get(TargetField::VariantEan->value);

        if ($isVariant && filled($ean) && (! ctype_digit((string) $ean) || ! in_array(strlen((string) $ean), self::EAN_LENGTHS, true))) {
            $messages[] = $this->warning('ean_length', TargetField::VariantEan->value, ['value' => $ean]);
        }

        if ($isVariant && ($stock = $values->note('product_stock')) !== null) {
            $messages[] = $this->warning('stock_not_split', TargetField::VariantStock->value, ['stock' => $stock]);
        }

        if ($isVariant) {
            $this->addDiscountWarnings($values, (int) $context['factor'], $messages);
            $this->addSaleWarning($values, (int) $context['factor'], $messages);
        }

        foreach ([TargetField::ProductUpsells, TargetField::ProductCrossSells] as $field) {
            foreach ((array) $values->get($field->value) as $reference) {
                if ($context['references']->resolve((string) $reference) === null) {
                    $messages[] = $this->warning($field === TargetField::ProductUpsells ? 'upsell_not_found' : 'cross_sell_not_found', $field->value, ['reference' => $reference]);
                }
            }
        }

        foreach ([TargetField::ProductImages, TargetField::VariantImage] as $field) {
            foreach ((array) $values->get($field->value) as $url) {
                if (! Str::isUrl((string) $url, ['http', 'https'])) {
                    $messages[] = $this->warning('image_not_web', $field->value, ['url' => $url]);
                }
            }
        }

        if ($action !== RowAction::Update) {
            return;
        }

        foreach ($values->values as $field => $value) {
            if ($value !== null || isset($values->invalid[$field]) || ! $this->reportsEmptyOnUpdate($field)) {
                continue;
            }

            // Product fields are taken from any row of the group.
            if ($this->isProductField($field) && $this->groupHas($group, $field)) {
                continue;
            }

            if (! $isVariant && ! $this->isProductField($field)) {
                continue;
            }

            $messages[] = $this->warning('stays_unchanged', $field, ['field' => $this->fieldLabel($field)]);
        }
    }

    /**
     * A discount that cannot be imported: both an amount and a percent above
     * zero, a percent above 100, or a start after its end.
     *
     * @param  list<array<string, mixed>>  $messages
     */
    protected function checkDiscount(NormalizedRow $values, array &$messages): void
    {
        $amount = $values->get(TargetField::VariantDiscountAmount->value);
        $percent = $values->get(TargetField::VariantDiscountPercent->value);
        $hasAmount = is_int($amount) && $amount > 0;
        $hasPercent = is_numeric($percent) && (float) $percent > 0;

        if (! $hasAmount && ! $hasPercent) {
            return;
        }

        if ($hasAmount && $hasPercent) {
            $messages[] = $this->error('discount_both', TargetField::VariantDiscountPercent->value);
        }

        if ($hasPercent && (float) $percent > 100) {
            $messages[] = $this->error('discount_percent_too_high', TargetField::VariantDiscountPercent->value, [
                'value' => (new RowDiscount(RowDiscount::PERCENT, (float) $percent))->valueText(),
            ]);
        }

        $starts = $values->get(TargetField::VariantSaleStarts->value);
        $ends = $values->get(TargetField::VariantSaleEnds->value);

        if (is_string($starts) && is_string($ends) && $starts > $ends) {
            $messages[] = $this->error('discount_dates', TargetField::VariantSaleStarts->value, [
                'start' => $this->day($starts),
                'end' => $this->day($ends),
            ]);
        }
    }

    /**
     * A discount that ended is not imported; a fixed amount that leaves less
     * than the shop's minimum share of the price is not applied by the shop.
     *
     * @param  list<array<string, mixed>>  $messages
     */
    protected function addDiscountWarnings(NormalizedRow $values, int $factor, array &$messages): void
    {
        $discount = RowDiscount::for($values);

        if ($discount === null) {
            return;
        }

        if ($discount->hasEnded()) {
            $messages[] = $this->warning('discount_ended', TargetField::VariantSaleEnds->value, ['date' => $this->day((string) $discount->endsAt)]);

            return;
        }

        $price = $values->get(TargetField::VariantPrice->value);

        if (is_int($price) && $discount->leavesTooLittleOf($price)) {
            $messages[] = $this->warning('discount_not_applied', TargetField::VariantDiscountAmount->value, [
                'amount' => $this->money((int) $discount->value, $factor),
                'percent' => Str::rtrim(Str::rtrim(number_format(RowDiscount::minimumRemainingPercentage(), 2, '.', ''), '0'), '.'),
            ]);
        }
    }

    /**
     * A sale price is not imported: a row whose sale price differs from its
     * price warns with both and the sale's dates, so staff can correct the
     * price to it.
     *
     * @param  list<array<string, mixed>>  $messages
     */
    protected function addSaleWarning(NormalizedRow $values, int $factor, array &$messages): void
    {
        $price = $values->get(TargetField::VariantPrice->value);
        $sale = $values->get(TargetField::VariantSale->value);

        if (! is_int($price) || ! is_int($sale) || $sale === $price) {
            return;
        }

        $starts = $values->get(TargetField::VariantSaleStarts->value);
        $ends = $values->get(TargetField::VariantSaleEnds->value);

        $code = match (true) {
            is_string($starts) && is_string($ends) => 'sale_price_between',
            is_string($starts) => 'sale_price_from',
            is_string($ends) => 'sale_price_until',
            default => 'sale_price',
        };

        $messages[] = $this->warning($code, TargetField::VariantSale->value, [
            'price' => $this->money($price, $factor),
            'sale' => $this->money($sale, $factor),
            'start' => is_string($starts) ? $this->day($starts) : '',
            'end' => is_string($ends) ? $this->day($ends) : '',
        ]);
    }

    /**
     * An amount in minor units as a number in major units with a dot.
     */
    protected function money(int $amount, int $factor): string
    {
        $factor = max(1, $factor);

        return number_format($amount / $factor, (int) round(log10($factor)), '.', '');
    }

    /**
     * A stored date as a day in the current language, for example "24 December 2026".
     */
    protected function day(string $date): string
    {
        return CarbonImmutable::parse($date)->locale(app()->getLocale())->translatedFormat('j F Y');
    }

    /**
     * Whether an empty mapped cell of the field is reported on an update.
     */
    protected function reportsEmptyOnUpdate(string $field): bool
    {
        $target = TargetField::tryFrom($field);

        // The status and the URLs of an existing product never change; lists are only added to.
        return ! in_array($field, [TargetField::VariantSku->value, TargetField::GroupKey->value, TargetField::ProductStatus->value], true)
            && ! Str::startsWith($field, ['variant.option.', 'variant.option_name.', 'variant.option_value.', 'source.', TargetField::ProductSlug->value.'.'])
            && ! ($target?->isList() ?? false)
            && ! in_array($target, [
                TargetField::VariantSale, TargetField::VariantSaleStarts, TargetField::VariantSaleEnds,
                TargetField::VariantDiscountAmount, TargetField::VariantDiscountPercent,
                TargetField::VariantPriceImpact, TargetField::VariantWeightImpact, TargetField::VariantImage,
            ], true);
    }

    /**
     * Whether a field belongs to the product rather than the variant.
     */
    protected function isProductField(string $field): bool
    {
        return Str::startsWith($field, ['product.', TargetField::ATTRIBUTE_PREFIX]);
    }

    /**
     * Whether any row of the group holds a value for the field.
     *
     * @param  array<int, NormalizedRow>  $group
     */
    protected function groupHas(array $group, string $field): bool
    {
        foreach ($group as $values) {
            if ($values->filled($field)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether staff skipped every category of the group, so the product ends
     * up without one.
     *
     * @param  array<int, NormalizedRow>  $group
     * @param  array<string, array<string, array<string, ?ValueAction>>>  $decisions
     */
    protected function categorySkipped(array $group, array $decisions): bool
    {
        $paths = [];

        foreach ($group as $values) {
            foreach (CategoryList::paths($values->get(TargetField::ProductCategory->value)) as $path) {
                $paths[] = $path;
            }
        }

        if ($paths === []) {
            return false;
        }

        foreach ($paths as $path) {
            if (($decisions[ValueType::Category->value][''][ValueKeys::category($path)] ?? null) !== ValueAction::Skip) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether staff skipped the group's brand.
     *
     * @param  array<int, NormalizedRow>  $group
     * @param  array<string, array<string, array<string, ?ValueAction>>>  $decisions
     */
    protected function brandSkipped(array $group, array $decisions): bool
    {
        foreach ($group as $values) {
            $brand = $values->get(TargetField::ProductBrand->value);

            if (filled($brand)) {
                return ($decisions[ValueType::Brand->value][''][ValueKeys::name((string) $brand)] ?? null) === ValueAction::Skip;
            }
        }

        return false;
    }

    /**
     * The first option value of the row that staff skipped.
     *
     * @param  array<string, array<string, array<string, ?ValueAction>>>  $decisions
     */
    protected function skippedOptionValue(NormalizedRow $values, SheetMapping $mapping, array $decisions): ?string
    {
        foreach ($mapping->optionKeys() as $key) {
            $value = $values->get('variant.option.'.$key);

            if (filled($value) && ($decisions[ValueType::OptionValue->value][$key][ValueKeys::name((string) $value)] ?? null) === ValueAction::Skip) {
                return (string) $value;
            }
        }

        foreach ($values->namedOptions() as $key => $value) {
            if (($decisions[ValueType::Option->value][''][Str::after($key, 'name:')] ?? null) === ValueAction::Skip) {
                continue;
            }

            if (($decisions[ValueType::OptionValue->value][$key][ValueKeys::name($value)] ?? null) === ValueAction::Skip) {
                return $value;
            }
        }

        return null;
    }

    /**
     * The duplicate match key error, naming the other rows.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function duplicateError(ProductImportRow $row, string $matchField, array $context): array
    {
        $others = array_filter($context['duplicates'][$row->match_value], fn (array $other): bool => $other['id'] !== $row->id);

        $rows = implode(', ', array_map(fn (array $other): string => __('lunarpanel.product-import::validation.messages.duplicate_row', [
            'sheet' => $context['sheets'][$other['sheet']]['name'] ?? '',
            'row' => $other['row'],
        ]), $others));

        return $this->error('duplicate', $matchField, [
            'field' => $this->fieldLabel($matchField),
            'value' => $row->match_value,
            'rows' => $rows,
        ]);
    }

    /**
     * The label of a field path in messages.
     */
    public function fieldLabel(string $field): string
    {
        $key = match (true) {
            $field === TargetField::VariantSku->value => 'sku',
            $field === TargetField::VariantEan->value => 'ean',
            $field === TargetField::VariantPrice->value => 'price',
            $field === TargetField::VariantStock->value => 'stock',
            $field === TargetField::VariantWeight->value => 'weight',
            Str::startsWith($field, TargetField::ProductName->value) => 'name',
            Str::startsWith($field, TargetField::ProductDescription->value) => 'description',
            $field === TargetField::ProductBrand->value => 'brand',
            $field === TargetField::ProductCategory->value => 'category',
            $field === TargetField::ProductStatus->value => 'status',
            Str::startsWith($field, TargetField::ProductSlug->value) => 'slug',
            default => null,
        };

        if ($key !== null) {
            return __('lunarpanel.product-import::validation.fields.'.$key);
        }

        if (Str::startsWith($field, TargetField::ATTRIBUTE_PREFIX)) {
            return __('lunarpanel.product-import::validation.fields.attribute', [
                'name' => Str::before(TargetField::attributeHandle($field), '.'),
            ]);
        }

        $target = TargetField::tryFrom($field) ?? TargetField::tryFrom(Str::beforeLast($field, '.'));

        return $target !== null ? Str::lcfirst($this->registry->label($target->value)) : $field;
    }

    /**
     * @param  array<string, mixed>  $replace
     * @return array{level: string, code: string, field: ?string, message: string}
     */
    protected function error(string $code, ?string $field = null, array $replace = []): array
    {
        return $this->message('error', $code, $field, $replace);
    }

    /**
     * @param  array<string, mixed>  $replace
     * @return array{level: string, code: string, field: ?string, message: string}
     */
    protected function warning(string $code, ?string $field = null, array $replace = []): array
    {
        return $this->message('warning', $code, $field, $replace);
    }

    /**
     * @param  array<string, mixed>  $replace
     * @return array{level: string, code: string, field: ?string, message: string}
     */
    protected function info(string $code, ?string $field = null, array $replace = []): array
    {
        return $this->message('info', $code, $field, $replace);
    }

    /**
     * @param  array<string, mixed>  $replace
     * @return array{level: string, code: string, field: ?string, message: string}
     */
    protected function message(string $level, string $code, ?string $field, array $replace): array
    {
        return [
            'level' => $level,
            'code' => $code,
            'field' => $field,
            'message' => Str::ucfirst(__('lunarpanel.product-import::validation.messages.'.$code, $replace)),
        ];
    }
}
