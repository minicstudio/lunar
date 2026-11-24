<?php

namespace Lunar\ProductImport\Enums;

/**
 * The status new products get: always draft, always published, or the
 * first status value of the product's rows in the mapped status column.
 */
enum NewProductStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case FromColumn = 'from_column';
}
