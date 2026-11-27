<?php

namespace Lunar\ProductImport\Enums;

/**
 * Why a row is skipped. Only a staff skip survives validating again.
 */
enum RowSkipReason: string
{
    case Staff = 'staff';
    case Existing = 'existing';
    case OptionValue = 'option_value';
    case RowType = 'row_type';
}
