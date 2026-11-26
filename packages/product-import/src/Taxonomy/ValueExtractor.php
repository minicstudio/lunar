<?php

namespace Lunar\ProductImport\Taxonomy;

use Lunar\Models\Currency;
use Lunar\ProductImport\Enums\TargetField;
use Lunar\ProductImport\Enums\ValueType;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Normalizing\CategoryList;
use Lunar\ProductImport\Normalizing\RowDiscount;
use Lunar\ProductImport\Structuring\RowStructurer;

/**
 * Collects the distinct categories, brands, option names from cells, option
 * values (per option), property names (per product type), and discounts of an
 * import's included sheets from the effective rows, with the sheets they
 * appear on and the products and variants that use them. Each path of a
 * category list is its own value; a product has a category when any of its
 * rows has one. A discount is collected from the variant rows that carry it,
 * one per kind, value, start, and end, unless it already ended.
 */
class ValueExtractor
{
    /**
     * @param  ?list<ValueType>  $types  The types to collect; null for every type.
     * @return array{values: list<ExtractedValue>, products_without_category: int}
     */
    public function extract(ProductImport $import, ?array $types = null): array
    {
        $wants = fn (ValueType $type): bool => $types === null || in_array($type, $types, true);
        $currency = $wants(ValueType::Discount) ? Currency::getDefault() : null;
        $factor = (int) ($currency?->factor ?? 100);

        $sheets = $import->sheets()->where('skipped', false)->get()->keyBy('id');
        $mappings = $sheets->map(fn ($sheet) => $sheet->sheetMapping());

        /** @var array<string, ExtractedValue> $values */
        $values = [];
        $groupsWithCategory = [];
        $groups = [];

        foreach (ProductImportRow::includedIn($import)->select(['id', 'product_import_sheet_id', 'group_key', 'role', 'normalized', 'overrides'])->lazyById(500) as $row) {
            /** @var ProductImportRow $row */
            $sheetName = (string) $sheets[$row->product_import_sheet_id]->name;
            $mapping = $mappings[$row->product_import_sheet_id];
            $effective = $row->effectiveRow();
            $group = (string) $row->group_key;

            // Rows whose row type is not imported have no values to review.
            if ($effective->note('skip') !== null) {
                continue;
            }

            $groups[$group] = true;

            foreach (CategoryList::paths($effective->get(TargetField::ProductCategory->value)) as $path) {
                $groupsWithCategory[$group] = true;
                $this->add($values, ValueType::Category, '', ValueKeys::category($path), implode(' > ', $path), $path, $sheetName, $group);
            }

            $brand = $effective->get(TargetField::ProductBrand->value);

            if (filled($brand)) {
                $this->add($values, ValueType::Brand, '', ValueKeys::name((string) $brand), (string) $brand, [(string) $brand], $sheetName, $group);
            }

            foreach ($mapping->optionKeys() as $optionKey) {
                $value = $effective->get('variant.option.'.$optionKey);

                if (filled($value)) {
                    $this->add($values, ValueType::OptionValue, $optionKey, ValueKeys::name((string) $value), (string) $value, [(string) $value], $sheetName, $group);
                }
            }

            foreach ((array) $effective->get(RowStructurer::NAMED_OPTIONS) as $option) {
                $name = (string) ($option['name'] ?? '');
                $value = (string) ($option['value'] ?? '');

                $this->add($values, ValueType::Option, '', ValueKeys::name($name), $name, [$name], $sheetName, $group);
                $this->add($values, ValueType::OptionValue, ValueKeys::namedOption($name), ValueKeys::name($value), $value, [$value], $sheetName, $group);
            }

            foreach ((array) $effective->get(RowStructurer::NAMED_PROPERTIES) as $property) {
                $name = (string) ($property['name'] ?? '');

                $this->add($values, ValueType::Property, ValueKeys::propertyType($mapping->productTypeId), ValueKeys::name($name), $name, [$name], $sheetName, $group);
            }

            $discount = $row->isVariantRow() && $wants(ValueType::Discount) ? RowDiscount::for($effective) : null;

            if ($discount !== null && ! $discount->hasEnded()) {
                $this->add($values, ValueType::Discount, '', ValueKeys::discount($discount), $discount->describe($factor, $currency?->code), [], $sheetName, $group, $discount->toArray());
            }
        }

        return [
            'values' => array_values(array_filter($values, fn (ExtractedValue $value): bool => $wants($value->type))),
            'products_without_category' => count(array_diff_key($groups, $groupsWithCategory)),
        ];
    }

    /**
     * Count one occurrence of a value. The first spelling found is kept.
     *
     * @param  array<string, ExtractedValue>  $values
     * @param  list<string>  $path
     * @param  ?array<string, mixed>  $details
     */
    protected function add(array &$values, ValueType $type, string $optionKey, string $normalizedKey, string $source, array $path, string $sheet, string $group, ?array $details = null): void
    {
        if ($normalizedKey === '') {
            return;
        }

        $value = new ExtractedValue($type, $optionKey, $normalizedKey, $source, $path, details: $details);
        $key = $value->key();

        $values[$key] ??= $value;
        $values[$key]->sheets[$sheet] = true;
        $values[$key]->groups[$group] = true;
        $values[$key]->variantCount++;
    }
}
