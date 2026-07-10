<?php

namespace Lunar\Loyalty\Contracts;

interface EarnCalculator
{
    /**
     * Calculate points to earn from the given context.
     *
     * @param  array{order_total_minor?: int, points?: int}  $context
     */
    public function calculate(array $context): int;
}
