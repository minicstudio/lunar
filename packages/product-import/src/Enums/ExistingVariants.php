<?php

namespace Lunar\ProductImport\Enums;

/**
 * What happens to variants that already exist.
 */
enum ExistingVariants: string
{
    case Update = 'update';
    case Skip = 'skip';
}
