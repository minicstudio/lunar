<?php

namespace Lunar\ProductImport\Validation;

use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportRow;

/**
 * The product groups to validate again after rows change: the rows' own
 * groups and the groups of rows sharing their match key values, so duplicate
 * errors appear and clear on both sides.
 */
class AffectedGroups
{
    /**
     * @param  iterable<ProductImportRow>  $rows
     * @param  list<string>  $extraMatchValues  Match values the rows had before the change.
     * @return list<string>
     */
    public static function for(ProductImport $import, iterable $rows, array $extraMatchValues = []): array
    {
        $groups = [];
        $values = $extraMatchValues;

        foreach ($rows as $row) {
            if ($row->group_key !== null) {
                $groups[$row->group_key] = true;
            }

            if ($row->match_value !== null) {
                $values[] = $row->match_value;
            }
        }

        $values = array_values(array_unique(array_filter($values, fn (?string $value): bool => $value !== null && $value !== '')));

        foreach (array_chunk($values, 500) as $chunk) {
            foreach ($import->rows()->whereIn('match_value', $chunk)->distinct()->pluck('group_key') as $groupKey) {
                $groups[(string) $groupKey] = true;
            }
        }

        return array_keys($groups);
    }
}
