<?php

namespace Lunar\Loyalty\Calculators;

use Lunar\Loyalty\Contracts\EarnCalculator;

final class OrderTotalCalculator implements EarnCalculator
{
    /**
     * {@inheritDoc}
     */
    public function calculate(array $context): int
    {
        $orderTotalMinor = $context['order_total_minor'] ?? 0;
        $ratio = (int) config('lunar.loyalty.currency.earn_ratio', 100);

        if ($ratio <= 0) {
            return 0;
        }

        return (int) floor($orderTotalMinor / $ratio);
    }
}
