<?php

namespace Lunar\ProductImport\Persisting;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lunar\DiscountTypes\AdvancedAmountOff;
use Lunar\Models\Brand;
use Lunar\Models\Channel;
use Lunar\Models\Collection;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Discount;
use Lunar\Models\ProductOption;
use Lunar\Models\ProductOptionValue;
use Lunar\ProductImport\Enums\TargetField;
use Lunar\ProductImport\Enums\ValueAction;
use Lunar\ProductImport\Enums\ValueType;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportValue;
use Lunar\ProductImport\Normalizing\RowDiscount;
use Lunar\ProductImport\Support\TargetFieldRegistry;
use Lunar\ProductImport\Taxonomy\PlannedRecord;
use Lunar\ProductImport\Taxonomy\PlannedRecords;
use RuntimeException;

/**
 * Creates the brands, categories, product options, option values, and
 * discounts staff decided to create, in one transaction, and writes the new
 * ids back to the values. Values that create the same record share one.
 */
class TaxonomyCreator
{
    public function __construct(
        protected PlannedRecords $planned,
        protected FieldValues $fields,
        protected TargetFieldRegistry $registry,
    ) {}

    /**
     * @return array{brands: list<array{id: int, name: string}>, categories: list<array{id: int, name: string}>, option_values: list<array{id: int, name: string}>, options: list<array{id: int, name: string}>, discounts: list<array{id: int, name: string}>}
     */
    public function create(ProductImport $import): array
    {
        $locale = $this->registry->defaultLanguage();
        $groupId = $import->importOptions()->collectionGroupId;

        return DB::transaction(function () use ($import, $locale, $groupId): array {
            $created = ['brands' => [], 'categories' => [], 'option_values' => [], 'options' => [], 'discounts' => []];

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

            $this->createDiscounts($import, $created);

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
     * The category is saved as a root first and then moved under its parent,
     * like the admin's child collections. A store's own collection class
     * fires each model event a second time as Lunar's class, so the tree's
     * saving listener runs twice: appending a node that is not saved yet
     * would let the second run turn it back into a root, away from its parent.
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

        $collection = new (Collection::modelClass())([
            'collection_group_id' => $parent?->collection_group_id ?? $groupId,
            'attribute_data' => collect(['name' => $this->fields->name(Collection::morphName(), $record->name, $locale)]),
        ]);

        $collection->save();

        if ($parent !== null) {
            $parent->appendNode($collection);
        }

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
     * Create the discounts decided as create, one per kind, value, start,
     * and end: amount or percent off without a coupon, starting when they
     * are created unless the decision gives a start, and enabled in every
     * channel and for every customer group.
     *
     * @param  array<string, list<array{id: int, name: string}>>  $created
     */
    protected function createDiscounts(ProductImport $import, array &$created): void
    {
        $values = $import->values()
            ->where('type', ValueType::Discount)
            ->where('action', ValueAction::Create)
            ->orderBy('id')
            ->get();

        /** @var array<string, int> $ids */
        $ids = [];

        foreach ($values as $value) {
            $discount = RowDiscount::fromArray($value->create_details ?? $value->details);

            if ($discount === null || ! $discount->isValid()) {
                throw new RuntimeException("The discount \"{$value->source_value}\" has no valid values.");
            }

            $key = $discount->key();

            if (! isset($ids[$key])) {
                $name = Str::squish((string) ($value->create_name ?: $value->source_value));
                $ids[$key] = $this->createDiscount($discount, $name);
                $created['discounts'][] = ['id' => $ids[$key], 'name' => $name];
            }

            $value->forceFill(['target_id' => $ids[$key]])->save();
        }
    }

    /**
     * Create one discount for every customer in every channel.
     */
    protected function createDiscount(RowDiscount $discount, string $name): int
    {
        $currency = $discount->kind === RowDiscount::AMOUNT ? Currency::getDefault() : null;

        throw_if($discount->kind === RowDiscount::AMOUNT && $currency === null, RuntimeException::class, 'No default currency is set.');

        $model = Discount::query()->create([
            'name' => $name,
            'handle' => $this->uniqueDiscountHandle($name),
            'type' => AdvancedAmountOff::class,
            'starts_at' => $discount->starts() ?? now(),
            'ends_at' => $discount->ends(),
            'data' => $discount->kind === RowDiscount::PERCENT
                ? ['fixed_value' => false, 'free_shipping' => false, 'percentage' => $discount->value]
                : ['fixed_value' => true, 'free_shipping' => false, 'fixed_values' => [(string) $currency?->code => (int) $discount->value]],
        ]);

        $model->channels()->sync(Channel::query()->pluck('id')->mapWithKeys(fn ($id): array => [$id => [
            'enabled' => true,
            'starts_at' => now(),
            'ends_at' => null,
        ]])->all());

        $model->customerGroups()->sync(CustomerGroup::query()->pluck('id')->mapWithKeys(fn ($id): array => [$id => [
            'enabled' => true,
            'visible' => true,
            'starts_at' => now(),
            'ends_at' => null,
        ]])->all());

        return (int) $model->id;
    }

    /**
     * A handle no other discount has.
     */
    protected function uniqueDiscountHandle(string $name): string
    {
        $base = Str::slug($name) ?: 'discount';
        $handle = $base;
        $suffix = 2;

        while (Discount::query()->where('handle', $handle)->exists()) {
            $handle = $base.'-'.$suffix++;
        }

        return $handle;
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
