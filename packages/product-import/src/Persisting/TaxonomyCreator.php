<?php

namespace Lunar\ProductImport\Persisting;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lunar\Models\Brand;
use Lunar\Models\Collection;
use Lunar\Models\ProductOption;
use Lunar\Models\ProductOptionValue;
use Lunar\ProductImport\Enums\TargetField;
use Lunar\ProductImport\Enums\ValueAction;
use Lunar\ProductImport\Enums\ValueType;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportValue;
use Lunar\ProductImport\Support\TargetFieldRegistry;
use Lunar\ProductImport\Taxonomy\PlannedRecord;
use Lunar\ProductImport\Taxonomy\PlannedRecords;
use RuntimeException;

/**
 * Creates the brands, categories, product options, and option values staff
 * decided to create, in one transaction, and writes the new ids back to the
 * values. Values that create the same record share one.
 */
class TaxonomyCreator
{
    public function __construct(
        protected PlannedRecords $planned,
        protected FieldValues $fields,
        protected TargetFieldRegistry $registry,
    ) {}

    /**
     * @return array{brands: list<array{id: int, name: string}>, categories: list<array{id: int, name: string}>, option_values: list<array{id: int, name: string}>, options: list<array{id: int, name: string}>}
     */
    public function create(ProductImport $import): array
    {
        $locale = $this->registry->defaultLanguage();
        $groupId = $import->importOptions()->collectionGroupId;

        return DB::transaction(function () use ($import, $locale, $groupId): array {
            $created = ['brands' => [], 'categories' => [], 'option_values' => [], 'options' => []];

            $options = $this->resolveOptions($import, $locale, $created);
            $import->forceFill(['resolved_options' => $options])->save();

            /** @var array<string, int> $ids */
            $ids = [];

            foreach ($this->planned->for($import) as $record) {
                $id = match ($record->type) {
                    ValueType::Brand => $this->createBrand($record),
                    ValueType::Category => $this->createCategory($record, $ids, $groupId, $locale),
                    ValueType::OptionValue => $this->createOptionValue($record, $options, $locale),
                };

                $ids[$record->key] = $id;
                $created[match ($record->type) {
                    ValueType::Brand => 'brands',
                    ValueType::Category => 'categories',
                    ValueType::OptionValue => 'option_values',
                }][] = ['id' => $id, 'name' => $record->name];

                if ($record->valueIds !== []) {
                    ProductImportValue::query()->whereIn('id', $record->valueIds)->update(['target_id' => $id]);
                }
            }

            return $created;
        });
    }

    /**
     * The option id per option key: existing options as they are, new options
     * created once from the mapping (handle slug, shared).
     *
     * @param  array<string, list<array{id: int, name: string}>>  $created
     * @return array<string, int>
     */
    protected function resolveOptions(ProductImport $import, string $locale, array &$created): array
    {
        $resolved = [];

        foreach ($import->sheets()->where('skipped', false)->get() as $sheet) {
            foreach ($sheet->sheetMapping()->columnsFor(TargetField::VariantOption) as $column) {
                $key = $column->optionKey();

                if ($key === null || isset($resolved[$key])) {
                    continue;
                }

                if ($column->optionId !== null) {
                    $resolved[$key] = $column->optionId;

                    continue;
                }

                $resolved[$key] = $this->createOption(Str::trim((string) ($column->optionName ?: $column->header)), $locale, $created);
            }
        }

        // Option names from cells: an existing option, or a new one shared
        // with a mapped new option of the same name.
        $namedOptions = $this->planned->namedOptions($import);

        foreach ($import->values()->where('type', ValueType::Option)->orderBy('id')->get() as $option) {
            $identity = $this->planned->optionIdentity('name:'.$option->normalized_key, $namedOptions);

            if ($identity === null) {
                continue;
            }

            $id = $resolved[$identity] ??= Str::startsWith($identity, 'option:')
                ? (int) Str::after($identity, 'option:')
                : $this->createOption(Str::squish((string) ($option->create_name ?: $option->source_value)), $locale, $created);

            $resolved['name:'.$option->normalized_key] = $id;

            if ($option->action === ValueAction::Create) {
                $option->forceFill(['target_id' => $id])->save();
            }
        }

        return $resolved;
    }

    /**
     * Create a shared option with a unique handle.
     *
     * @param  array<string, list<array{id: int, name: string}>>  $created
     */
    protected function createOption(string $name, string $locale, array &$created): int
    {
        $option = ProductOption::query()->create([
            'name' => [$locale => $name],
            'label' => [$locale => $name],
            'handle' => $this->uniqueHandle($name),
            'shared' => true,
        ]);

        $created['options'][] = ['id' => (int) $option->id, 'name' => $name];

        return (int) $option->id;
    }

    protected function createBrand(PlannedRecord $record): int
    {
        return (int) Brand::query()->create(['name' => $record->name])->id;
    }

    /**
     * Create a category under its decided parent, or as a root in the group.
     *
     * @param  array<string, int>  $ids
     */
    protected function createCategory(PlannedRecord $record, array $ids, ?int $groupId, string $locale): int
    {
        $parentId = $record->parentKey !== null ? ($ids[$record->parentKey] ?? null) : $record->parentId;
        $parent = $parentId !== null ? Collection::query()->find($parentId) : null;

        if ($parentId !== null && $parent === null) {
            throw new RuntimeException("The parent category [{$parentId}] of \"{$record->name}\" does not exist.");
        }

        $collection = new Collection([
            'collection_group_id' => $parent?->collection_group_id ?? $groupId,
            'attribute_data' => collect(['name' => $this->fields->name(Collection::morphName(), $record->name, $locale)]),
        ]);

        $parent ? $parent->appendNode($collection) : $collection->save();

        return (int) $collection->id;
    }

    /**
     * Create an option value in its option.
     *
     * @param  array<string, int>  $options
     */
    protected function createOptionValue(PlannedRecord $record, array $options, string $locale): int
    {
        $optionId = $options[$record->optionKey] ?? (Str::startsWith((string) $record->optionKey, 'option:') ? (int) Str::after((string) $record->optionKey, 'option:') : null);

        if ($optionId === null) {
            throw new RuntimeException("The option of the value \"{$record->name}\" is not mapped.");
        }

        $position = (int) ProductOptionValue::query()->where('product_option_id', $optionId)->max('position') + 1;

        return (int) ProductOptionValue::query()->create([
            'product_option_id' => $optionId,
            'name' => [$locale => $record->name],
            'position' => $position,
        ])->id;
    }

    /**
     * A handle no other option has.
     */
    protected function uniqueHandle(string $name): string
    {
        $base = Str::slug($name) ?: 'option';
        $handle = $base;
        $suffix = 2;

        while (ProductOption::query()->where('handle', $handle)->exists()) {
            $handle = $base.'-'.$suffix++;
        }

        return $handle;
    }
}
