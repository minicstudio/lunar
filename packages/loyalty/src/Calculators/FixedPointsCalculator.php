<?php

namespace Lunar\Loyalty\Calculators;

use Lunar\Loyalty\Contracts\EarnCalculator;

final class FixedPointsCalculator implements EarnCalculator
{
    /**
     * {@inheritDoc}
     */
    public function calculate(array $context): int
    {
        return (int) ($context['points'] ?? 0);
    }
}
