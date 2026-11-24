<?php

namespace Lunar\ProductImport\Taxonomy;

use Lunar\ProductImport\Enums\TargetField;
use Lunar\ProductImport\Enums\ValueType;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Normalizing\CategoryList;
use Lunar\ProductImport\Structuring\RowStructurer;

/**
 * Collects the distinct categories, brands, option names from cells, option
 * values (per option), and property names (per product type) of an import's
 * included sheets from the effective rows, with the sheets they appear on and
 * the products and variants that use them. Each path of a category list is
 * its own value; a product has a category when any of its rows has one.
 */
class ValueExtractor
{
    /**
     * @return array{values: list<ExtractedValue>, products_without_category: int}
     */
    public function extract(ProductImport $import): array
    {
        $sheets = $import->sheets()->where('skipped', false)->get()->keyBy('id');
        $mappings = $sheets->map(fn ($sheet) => $sheet->sheetMapping());

        /** @var array<string, ExtractedValue> $values */
        $values = [];
        $groupsWithCategory = [];
        $groups = [];

        foreach (ProductImportRow::includedIn($import)->select(['id', 'product_import_sheet_id', 'group_key', 'normalized', 'overrides'])->lazyById(500) as $row) {
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
        }

        return [
            'values' => array_values($values),
            'products_without_category' => count(array_diff_key($groups, $groupsWithCategory)),
        ];
    }

    /**
     * Count one occurrence of a value. The first spelling found is kept.
     *
     * @param  array<string, ExtractedValue>  $values
     * @param  list<string>  $path
     */
    protected function add(array &$values, ValueType $type, string $optionKey, string $normalizedKey, string $source, array $path, string $sheet, string $group): void
    {
        if ($normalizedKey === '') {
            return;
        }

        $value = new ExtractedValue($type, $optionKey, $normalizedKey, $source, $path);
        $key = $value->key();

        $values[$key] ??= $value;
        $values[$key]->sheets[$sheet] = true;
        $values[$key]->groups[$group] = true;
        $values[$key]->variantCount++;
    }
}
