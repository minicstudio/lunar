<?php

namespace Lunar\ProductImport\Enums;

/**
 * How rows form products: by a group key column, by a parent reference
 * (across sheets), by product name, or one product per row.
 */
enum GroupingStrategy: string
{
    case ByColumn = 'by_column';
    case ByParent = 'by_parent';
    case ByName = 'by_name';
    case PerRow = 'per_row';
}
