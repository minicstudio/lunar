<?php

namespace Lunar\ProductImport\Enums;

enum MatchType: string
{
    case Exact = 'exact';
    case Partial = 'partial';
    case Similar = 'similar';
    case None = 'none';
}
