<?php

namespace Lunar\ProductImport\Structuring;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lunar\ProductImport\Data\NormalizedRow;
use Lunar\ProductImport\Data\SheetMapping;
use Lunar\ProductImport\Enums\GroupingStrategy;
use Lunar\ProductImport\Enums\RowRole;
use Lunar\ProductImport\Enums\TargetField;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Support\KeyNormalizer;

/**
 * Turns normalized rows into products and variants. It decides each row's
 * role from its row type (`RowTypes`) or the general rule, resolves parent
 * references across the included sheets, joins rows into the group of their
 * parent, completes variant rows from their product row (inherited fields,
 * price and weight impacts, pair names, and a row type that is not
 * shippable), and reads options and properties from cells. What it derives
 * is stored apart from the normalized values, so structuring again starts
 * from the cells.
 */
class RowStructurer
{
    /**
     * Rows handled per chunk.
     */
    public const CHUNK = 500;

    /**
     * Fields a variant row takes from its product row when its own cell is empty.
     *
     * @var list<TargetField>
     */
    public const INHERITED = [
        TargetField::VariantWeight,
        TargetField::VariantLength,
        TargetField::VariantWidth,
        TargetField::VariantHeight,
        TargetField::VariantStockStatus,
        TargetField::VariantBackorders,
        TargetField::VariantMinQuantity,
        TargetField::VariantShippable,
    ];

    /**
     * The derived field of options read from cells.
     */
    public const NAMED_OPTIONS = 'variant.named_options';

    /**
     * The derived field of properties read from cells.
     */
    public const NAMED_PROPERTIES = 'product.named_properties';

    /**
     * The derived fields of the units of inherited weights and dimensions.
     */
    public const WEIGHT_UNIT = 'variant.weight_unit';

    public const LENGTH_UNIT = 'variant.length_unit';

    /**
     * Structure every included row of the import.
     */
    public function structure(ProductImport $import): void
    {
        $mappings = $this->mappings($import);
        $rows = [];
        $resolver = new ParentResolver;

        ProductImportRow::includedIn($import)
            ->select(['id', 'product_import_sheet_id', 'row_number', 'normalized', 'overrides', 'group_key'])
            ->chunkById(self::CHUNK, function (Collection $chunk) use ($mappings, &$rows, $resolver): void {
                foreach ($chunk as $row) {
                    /** @var ProductImportRow $row */
                    $mapping = $mappings[$row->product_import_sheet_id];
                    $values = NormalizedRow::effective($row->normalized, $row->overrides, false);
                    $role = RowTypes::read($mapping, $values)->role;
                    $sourceId = $this->text($values->get(TargetField::SourceId->value));
                    $sku = $this->text($values->get(TargetField::VariantSku->value));

                    $rows[$row->id] = [
                        'group' => (string) $row->group_key,
                        'source_id' => $sourceId,
                        'parent_ref' => $mapping->grouping->strategy === GroupingStrategy::ByParent
                            ? $this->text($values->get(TargetField::SourceParent->value))
                            : null,
                        'role' => $role instanceof RowRole ? $role : null,
                        'skip' => $role instanceof SkippedRow ? $role->type : null,
                    ];

                    if (! $role instanceof SkippedRow) {
                        $resolver->addRow($row->id, $sourceId, $sku);
                    }
                }
            });

        $resolver->preloadExisting(array_filter(array_column($rows, 'parent_ref')));

        $parents = [];
        $named = [];

        foreach ($rows as $id => $info) {
            if ($info['parent_ref'] === null || $info['skip'] !== null) {
                continue;
            }

            $resolved = $resolver->resolve($info['parent_ref']);

            if (($resolved['row'] ?? null) === $id) {
                $resolved = null;
            }

            $parents[$id] = $resolved ?? ['unresolved' => $info['parent_ref']];

            if (isset($resolved['row'])) {
                $named[$resolved['row']] = true;
            }
        }

        $groups = [];

        foreach ($rows as $id => $info) {
            $groups[$id] = $this->groupKey($id, $rows, $parents);

            $rows[$id]['role'] = $info['role'] ?? match (true) {
                $info['skip'] !== null => null,
                isset($parents[$id]) => RowRole::Variant,
                isset($named[$id]) => RowRole::Product,
                default => RowRole::ProductAndVariant,
            };
        }

        foreach (array_chunk(array_keys($rows), self::CHUNK) as $ids) {
            DB::transaction(function () use ($ids, $rows, $groups): void {
                foreach ($ids as $id) {
                    ProductImportRow::query()->whereKey($id)->update([
                        'role' => $rows[$id]['role']?->value,
                        'source_id' => $rows[$id]['source_id'] !== null ? Str::limit($rows[$id]['source_id'], 250, '') : null,
                        'parent_ref' => $rows[$id]['parent_ref'] !== null ? Str::limit($rows[$id]['parent_ref'], 250, '') : null,
                        'group_key' => $groups[$id],
                    ]);
                }
            });
        }

        $notes = [];

        foreach ($rows as $id => $info) {
            if ($info['skip'] !== null) {
                $notes[$id]['skip'] = $info['skip'];
            }

            if (isset($parents[$id]['unresolved'])) {
                $notes[$id]['unresolved_parent'] = $parents[$id]['unresolved'];
            }
        }

        $this->completeGroups($import, null, $notes);
    }

    /**
     * Complete the rows of the given groups again, keeping their roles, for
     * example after a row correction.
     *
     * @param  list<string>  $groupKeys
     */
    public function structureGroups(ProductImport $import, array $groupKeys): void
    {
        if ($groupKeys === []) {
            return;
        }

        $notes = [];

        ProductImportRow::includedIn($import)
            ->whereIn('group_key', $groupKeys)
            ->select(['id', 'normalized'])
            ->chunkById(self::CHUNK, function (Collection $chunk) use (&$notes): void {
                foreach ($chunk as $row) {
                    $kept = array_intersect_key(NormalizedRow::fromArray($row->normalized)->notes, array_flip(['skip', 'unresolved_parent']));

                    if ($kept !== []) {
                        $notes[$row->id] = $kept;
                    }
                }
            });

        $this->completeGroups($import, array_values(array_unique($groupKeys)), $notes);
    }

    /**
     * Complete the variant rows of every group (or the given ones) from their
     * product row and read options and properties from cells.
     *
     * @param  ?list<string>  $groupKeys
     * @param  array<int, array<string, mixed>>  $notes  Structure notes by row id.
     */
    protected function completeGroups(ProductImport $import, ?array $groupKeys, array $notes): void
    {
        $mappings = $this->mappings($import);
        $group = [];
        $groupKey = null;

        $rows = ProductImportRow::includedIn($import)
            ->when($groupKeys !== null, fn ($query) => $query->whereIn('group_key', $groupKeys))
            ->select(['id', 'product_import_sheet_id', 'row_number', 'normalized', 'overrides', 'group_key', 'role'])
            ->orderBy('group_key')
            ->orderBy('product_import_sheet_id')
            ->orderBy('row_number');

        $flush = function () use (&$group, $mappings, $notes): void {
            if ($group !== []) {
                DB::transaction(fn () => $this->completeGroup($group, $mappings, $notes));
            }

            $group = [];
        };

        foreach ($rows->lazy(self::CHUNK) as $row) {
            if ($groupKey !== null && $row->group_key !== $groupKey) {
                $flush();
            }

            $groupKey = $row->group_key;
            $group[] = $row;
        }

        $flush();
    }

    /**
     * Complete one group and store each row's derived values and notes.
     *
     * @param  list<ProductImportRow>  $rows
     * @param  array<int, SheetMapping>  $mappings
     * @param  array<int, array<string, mixed>>  $notes
     */
    protected function completeGroup(array $rows, array $mappings, array $notes): void
    {
        $base = [];
        $types = [];

        foreach ($rows as $row) {
            $base[$row->id] = NormalizedRow::effective($row->normalized, $row->overrides, false);
            $types[$row->id] = RowTypes::read($mappings[$row->product_import_sheet_id], $base[$row->id]);
        }

        $productRow = null;

        foreach ($rows as $row) {
            if ($row->role === RowRole::Product) {
                $productRow = $row;

                break;
            }
        }

        $derived = [];
        $rowNotes = [];
        $optionNames = [];

        // Variant rows first: product rows need the group's option names.
        foreach ($rows as $row) {
            $derived[$row->id] = [];
            $rowNotes[$row->id] = $notes[$row->id] ?? [];

            if ($row->role !== RowRole::Variant) {
                continue;
            }

            $values = $base[$row->id];

            if ($productRow !== null) {
                $this->inherit($values, $base[$productRow->id], $mappings[$row->product_import_sheet_id], $mappings[$productRow->product_import_sheet_id], $derived[$row->id], $rowNotes[$row->id]);
            }

            $this->impacts($values, $productRow !== null ? $base[$productRow->id] : null, $derived[$row->id]);

            [$options, $missing] = $this->cellOptions($values, $derived[$row->id], $productRow !== null ? $base[$productRow->id] : null);

            $derived[$row->id][self::NAMED_OPTIONS] = $options;

            if ($missing !== []) {
                $rowNotes[$row->id]['missing_option_values'] = $missing;
            }

            foreach ($options as $option) {
                $optionNames[KeyNormalizer::key($option['name'])] = true;
            }
        }

        foreach ($rows as $row) {
            if ($row->role === RowRole::Variant || $row->role === null) {
                continue;
            }

            $values = $base[$row->id];
            [$options, $missing] = $this->cellOptions($values, $derived[$row->id], null);
            $properties = (array) $values->get(TargetField::ProductProperties->value);

            if ($row->role->isVariant()) {
                $this->impacts($values, null, $derived[$row->id]);
            }

            if ($row->role === RowRole::Product) {
                foreach ($options as $option) {
                    if (! isset($optionNames[KeyNormalizer::key($option['name'])])) {
                        $properties[] = $option;
                    }
                }
            } elseif ($types[$row->id]->isSingle()) {
                // A single variant has no options to choose between.
                array_push($properties, ...$options);
            } else {
                $derived[$row->id][self::NAMED_OPTIONS] = $options;

                if ($missing !== []) {
                    $rowNotes[$row->id]['missing_option_values'] = $missing;
                }
            }

            $derived[$row->id][self::NAMED_PROPERTIES] = $properties;
        }

        foreach ($rows as $row) {
            if ($row->role?->isVariant() && $types[$row->id]->notShippable && ! $base[$row->id]->filled(TargetField::VariantShippable->value)) {
                $derived[$row->id][TargetField::VariantShippable->value] = false;
            }
        }

        foreach ($rows as $row) {
            $stored = NormalizedRow::fromArray($row->normalized);
            $stored->derived = array_filter($derived[$row->id], fn (mixed $value): bool => $value !== null && $value !== []);
            $stored->notes = $rowNotes[$row->id];

            ProductImportRow::query()->whereKey($row->id)->update([
                'normalized' => json_encode($stored->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }
    }

    /**
     * Take the product row's values that the variant row lacks: the inherited
     * fields, the base of a price or weight impact, and a note of the product
     * row's stock when the variant has none.
     *
     * @param  array<string, mixed>  $derived
     * @param  array<string, mixed>  $notes
     */
    protected function inherit(NormalizedRow $values, NormalizedRow $product, SheetMapping $mapping, SheetMapping $productMapping, array &$derived, array &$notes): void
    {
        foreach (self::INHERITED as $field) {
            if (! $values->filled($field->value) && $product->filled($field->value)) {
                $derived[$field->value] = $product->get($field->value);

                if ($field === TargetField::VariantWeight && $productMapping->weightUnit !== $mapping->weightUnit) {
                    $derived[self::WEIGHT_UNIT] = $productMapping->weightUnit;
                }

                if ($field->isDimension() && $productMapping->lengthUnit !== $mapping->lengthUnit) {
                    $derived[self::LENGTH_UNIT] = $productMapping->lengthUnit;
                }
            }
        }

        if (! $values->filled(TargetField::VariantStock->value) && $product->filled(TargetField::VariantStock->value)) {
            $notes['product_stock'] = $product->get(TargetField::VariantStock->value);
        }
    }

    /**
     * Apply a price or weight impact: the base value plus the impact, where
     * the base is the row's own value or, when empty, its product row's.
     *
     * @param  array<string, mixed>  $derived
     */
    protected function impacts(NormalizedRow $values, ?NormalizedRow $product, array &$derived): void
    {
        $impact = $values->get(TargetField::VariantPriceImpact->value);
        $price = $values->get(TargetField::VariantPrice->value) ?? $product?->get(TargetField::VariantPrice->value);

        if (is_int($impact) && is_int($price)) {
            $derived[TargetField::VariantPrice->value] = $price + $impact;
        }

        $impact = $values->get(TargetField::VariantWeightImpact->value);
        $weight = $derived[TargetField::VariantWeight->value] ?? $values->get(TargetField::VariantWeight->value) ?? $product?->get(TargetField::VariantWeight->value);

        if (is_numeric($impact) && is_numeric($weight)) {
            $derived[TargetField::VariantWeight->value] = round((float) $weight + (float) $impact, 4);
        }
    }

    /**
     * The options a row names in its cells: its option name and value pairs,
     * paired by position, and its `Name:Value` items. A pair value without a
     * name takes the name of the same pair from the product row. Names
     * without a value are returned apart.
     *
     * @param  array<string, mixed>  $derived
     * @return array{0: list<array{name: string, value: string}>, 1: list<string>}
     */
    protected function cellOptions(NormalizedRow $values, array &$derived, ?NormalizedRow $product): array
    {
        $options = [];
        $missing = [];
        $seen = [];

        foreach (array_keys($values->values) as $field) {
            if (! Str::startsWith($field, TargetField::VariantOptionValue->value.'.') && ! Str::startsWith($field, TargetField::VariantOptionName->value.'.')) {
                continue;
            }

            $pair = Str::afterLast($field, '.');

            if (isset($seen[$pair])) {
                continue;
            }

            $seen[$pair] = true;
            $nameField = TargetField::VariantOptionName->value.'.'.$pair;
            $names = array_values((array) $values->get($nameField));
            $items = array_values((array) $values->get(TargetField::VariantOptionValue->value.'.'.$pair));

            if (array_filter($names) === [] && array_filter($items) !== [] && $product !== null && array_filter((array) $product->get($nameField)) !== []) {
                $names = array_values((array) $product->get($nameField));
                $derived[$nameField] = $names;
            }

            // One name with a list of values: the name holds all of them.
            if (count($names) === 1 && count($items) > 1) {
                $items = [implode(', ', array_filter($items, fn (string $item): bool => $item !== ''))];
            }

            foreach ($names as $index => $name) {
                $name = (string) $name;
                $value = (string) ($items[$index] ?? '');

                if ($name === '') {
                    continue;
                }

                $value === ''
                    ? $missing[] = $name
                    : $options[] = ['name' => $name, 'value' => $value];
            }
        }

        foreach ((array) $values->get(TargetField::VariantOptions->value) as $item) {
            if (is_array($item) && isset($item['name'], $item['value'])) {
                $options[] = ['name' => (string) $item['name'], 'value' => (string) $item['value']];
            }
        }

        return [$options, array_values(array_unique($missing))];
    }

    /**
     * The group a row ends up in: its parent row's group, the existing product
     * its parent reference names, or its own.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, array<string, mixed>>  $parents
     */
    protected function groupKey(int $id, array $rows, array $parents): string
    {
        $seen = [];

        while (isset($parents[$id]['row']) && ! isset($seen[$id])) {
            $seen[$id] = true;
            $id = $parents[$id]['row'];
        }

        if (isset($parents[$id]['product'])) {
            return 'product:'.$parents[$id]['product'];
        }

        return $rows[$id]['group'];
    }

    /**
     * The mappings of the import's included sheets, by sheet id.
     *
     * @return array<int, SheetMapping>
     */
    protected function mappings(ProductImport $import): array
    {
        return $import->sheets()->get()
            ->mapWithKeys(fn ($sheet): array => [$sheet->id => $sheet->sheetMapping()])
            ->all();
    }

    /**
     * A value as trimmed text, or null when it is empty.
     */
    protected function text(mixed $value): ?string
    {
        $text = is_scalar($value) ? Str::trim((string) $value) : '';

        return $text === '' ? null : $text;
    }
}
