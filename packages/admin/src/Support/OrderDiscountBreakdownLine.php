<?php

namespace Lunar\Admin\Support;

use Lunar\DataTypes\Price;

class OrderDiscountBreakdownLine
{
    public function __construct(
        public string $type,
        public ?string $detail,
        public Price $amount,
    ) {}
}
