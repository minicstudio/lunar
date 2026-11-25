<?php

namespace Lunar\ProductImport\Taxonomy;

use Illuminate\Database\Eloquent\Builder;
use Lunar\DiscountTypes\AdvancedAmountOff;
use Lunar\Models\Brand;
use Lunar\Models\Collection;
use Lunar\Models\Currency;
use Lunar\Models\Discount;
use Lunar\Models\Product;
use Lunar\Models\ProductOption;
use Lunar\Models\ProductOptionValue;
use Lunar\Models\ProductVariant;
use Lunar\ProductImport\Normalizing\RowDiscount;
use Lunar\ProductImport\Support\KeyNormalizer;
use Lunar\ProductImport\Support\TargetFieldRegistry;

/**
 * The brands, one collection group's tree, the product options and their
 * values, the product types' text attributes, and the eligible discounts,
 * loaded once and keyed for matching in memory.
 */
class CatalogSnapshot
{
    /**
     * @var array<int, string> Brand id => normalized name.
     */
    public array $brands = [];

    /**
     * @var array<int, array{parent: ?int, key: string, name: string}> Collection id => node.
     */
    public array $collections = [];

    /**
     * @var array<string, list<int>> Parent id ('' for roots) => child ids.
     */
    public array $children = [];

    /**
     * @var array<int, array<int, list<string>>> Option id => value id => normalized names.
     */
    public array $optionValues = [];

    /**
     * @var array<int, list<string>> Option id => normalized names in every language.
     */
    public array $options = [];

    /**
     * @var array<int, array<int, list<string>>> Product type id => attribute id => normalized names, of the text and translated text attributes.
     */
    public array $attributes = [];

    /**
     * @var array<string, int> Normalized option name from cells => the existing option it uses.
     */
    public array $namedOptions = [];

    /**
     * @var array<int, array{name: string, kind: string, value: int|float, starts_at: ?string, ends_at: ?string}> Eligible discount id => its values, the amount for the default currency.
     */
    public array $discounts = [];

    /**
     * Load the catalog parts an import's values are matched against.
     *
     * @param  list<int>  $optionIds
     */
    public static function load(?int $collectionGroupId, array $optionIds, string $locale, array $productTypeIds = []): self
    {
        $snapshot = new self;

        foreach (ProductOption::query()->get(['id', 'name', 'label']) as $option) {
            $snapshot->options[(int) $option->id] = static::keys([$option->name, $option->label]);
        }

        foreach (array_unique(array_filter($productTypeIds)) as $productTypeId) {
            $snapshot->attributes[$productTypeId] = [];

            foreach (app(TargetFieldRegistry::class)->textAttributes((int) $productTypeId) as $attribute) {
                $snapshot->attributes[$productTypeId][(int) $attribute->id] = [...static::keys([$attribute->name]), KeyNormalizer::key($attribute->handle)];
            }
        }

        foreach (Brand::query()->get(['id', 'name']) as $brand) {
            $snapshot->brands[(int) $brand->id] = KeyNormalizer::key($brand->name);
        }

        if ($collectionGroupId !== null) {
            foreach (Collection::query()->where('collection_group_id', $collectionGroupId)->orderBy('_lft')->get(['id', 'parent_id', 'attribute_data']) as $collection) {
                $name = (string) ($collection->translateAttribute('name', $locale) ?? '');

                $snapshot->collections[(int) $collection->id] = [
                    'parent' => $collection->parent_id !== null ? (int) $collection->parent_id : null,
                    'key' => KeyNormalizer::key($name),
                    'name' => $name,
                ];

                $snapshot->children[(string) ($collection->parent_id ?? '')][] = (int) $collection->id;
            }
        }

        $snapshot->loadOptionValues($optionIds);

        return $snapshot;
    }

    /**
     * Load the values of the given options, unless they are loaded.
     *
     * @param  list<int>  $optionIds
     */
    public function loadOptionValues(array $optionIds): void
    {
        $optionIds = array_values(array_diff(array_unique($optionIds), array_keys($this->optionValues)));

        if ($optionIds === []) {
            return;
        }

        foreach ($optionIds as $optionId) {
            $this->optionValues[$optionId] = [];
        }

        foreach (ProductOptionValue::query()->whereIn('product_option_id', $optionIds)->get(['id', 'product_option_id', 'name']) as $value) {
            $names = array_values(array_filter(array_map(
                fn (mixed $name): string => KeyNormalizer::key(is_string($name) ? $name : null),
                (array) ($value->name?->getArrayCopy() ?? []),
            )));

            $this->optionValues[(int) $value->product_option_id][(int) $value->id] = $names;
        }
    }

    /**
     * The normalized names of translated values, in every language.
     *
     * @param  list<mixed>  $translations
     * @return list<string>
     */
    protected static function keys(array $translations): array
    {
        $keys = [];

        foreach ($translations as $translation) {
            $names = is_object($translation) && method_exists($translation, 'getArrayCopy') ? $translation->getArrayCopy() : (array) $translation;

            foreach ($names as $name) {
                $key = KeyNormalizer::key(is_string($name) ? $name : null);

                if ($key !== '') {
                    $keys[$key] = true;
                }
            }
        }

        return array_keys($keys);
    }

    /**
     * The path of a collection, root first.
     *
     * @return list<string>
     */
    public function collectionPath(int $id): array
    {
        $path = [];
        $current = $id;

        while ($current !== null && isset($this->collections[$current])) {
            array_unshift($path, $this->collections[$current]['name']);
            $current = $this->collections[$current]['parent'];
        }

        return $path;
    }

    /**
     * Load the eligible discounts (`eligibleDiscounts()`) with their kind,
     * value (the amount for the default currency), start, and end.
     */
    public function loadDiscounts(): void
    {
        $currency = (string) Currency::getDefault()?->code;

        foreach (static::eligibleDiscounts()->orderBy('id')->get(['id', 'name', 'data', 'starts_at', 'ends_at']) as $discount) {
            $data = $discount->data ?? [];
            $fixed = (bool) ($data['fixed_value'] ?? false);

            $this->discounts[(int) $discount->id] = [
                'name' => (string) $discount->name,
                'kind' => $fixed ? RowDiscount::AMOUNT : RowDiscount::PERCENT,
                'value' => $fixed ? (int) ($data['fixed_values'][$currency] ?? 0) : round((float) ($data['percentage'] ?? 0), 4),
                'starts_at' => $discount->starts_at?->format('Y-m-d H:i:s'),
                'ends_at' => $discount->ends_at?->format('Y-m-d H:i:s'),
            ];
        }
    }

    /**
     * The discounts an import may add variants to: amount or percent off
     * (`AdvancedAmountOff`), without a coupon, and limited to products or
     * variants and to nothing else. Adding a variant to a discount limited by
     * collection or brand, or to one without limits, would change which other
     * products it reaches.
     *
     * @return Builder<Discount>
     */
    public static function eligibleDiscounts(): Builder
    {
        $purchasables = [Product::morphName(), ProductVariant::morphName()];

        return Discount::query()
            ->where('type', AdvancedAmountOff::class)
            ->where(fn (Builder $query) => $query->whereNull('coupon')->orWhere('coupon', ''))
            ->whereHas('discountables', fn (Builder $query) => $query->where('type', 'limitation')->whereIn('discountable_type', $purchasables))
            ->whereDoesntHave('discountables', fn (Builder $query) => $query->where(
                fn (Builder $query) => $query->whereNotIn('type', ['limitation', 'exclusion'])->orWhereNotIn('discountable_type', $purchasables)
            ))
            ->whereDoesntHave('collections')
            ->whereDoesntHave('brands');
    }
}
