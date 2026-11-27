<?php

namespace Lunar\ProductImport\Grouping;

use Illuminate\Support\Str;
use Lunar\ProductImport\Data\NormalizedRow;
use Lunar\ProductImport\Data\SheetMapping;
use Lunar\ProductImport\Enums\GroupingStrategy;
use Lunar\ProductImport\Enums\TargetField;
use Lunar\ProductImport\Support\KeyNormalizer;

/**
 * Assigns each row the key of the product it belongs to. Rows of the same
 * sheet with the same group value form one product; rows never group across
 * sheets. A row without a group value is its own product.
 */
class ProductGrouper
{
    /**
     * The group key of a row.
     */
    public function groupKey(int $sheetId, int $rowNumber, NormalizedRow $row, SheetMapping $mapping, string $defaultLocale): string
    {
        $value = match ($mapping->grouping->strategy) {
            GroupingStrategy::ByColumn => Str::squish((string) $row->get(TargetField::GroupKey->value)),
            GroupingStrategy::ByName => KeyNormalizer::key($this->name($row, $defaultLocale)),
            // Grouping by parent is decided by `RowStructurer`, across sheets.
            GroupingStrategy::ByParent, GroupingStrategy::PerRow => '',
        };

        return $value === ''
            ? $sheetId.':row:'.$rowNumber
            : $sheetId.':'.$value;
    }

    /**
     * The product name in the default language, or in any language.
     */
    protected function name(NormalizedRow $row, string $defaultLocale): string
    {
        $names = array_filter($row->withPrefix(TargetField::ProductName->value.'.'), fn (mixed $name): bool => filled($name));

        return (string) ($names[$defaultLocale] ?? reset($names) ?: '');
    }
}
