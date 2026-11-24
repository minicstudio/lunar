<?php

namespace Lunar\ProductImport\Enums;

enum ValueType: string
{
    case Category = 'category';
    case Brand = 'brand';
    case Option = 'option';
    case OptionValue = 'option_value';
    case Property = 'property';
}
