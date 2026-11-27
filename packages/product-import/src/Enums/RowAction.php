<?php

namespace Lunar\ProductImport\Enums;

/**
 * What the commit does with a row's variant.
 */
enum RowAction: string
{
    case Create = 'create';
    case Update = 'update';
}
