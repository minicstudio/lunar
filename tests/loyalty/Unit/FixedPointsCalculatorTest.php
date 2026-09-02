<?php

use Lunar\Loyalty\Calculators\FixedPointsCalculator;

it('returns the points value from context', function () {
    $calculator = new FixedPointsCalculator;

    expect($calculator->calculate(['points' => 500]))->toBe(500)
        ->and($calculator->calculate(['points' => 0]))->toBe(0)
        ->and($calculator->calculate(['points' => 1]))->toBe(1);
});

it('returns zero when points key is absent', function () {
    $calculator = new FixedPointsCalculator;

    expect($calculator->calculate([]))->toBe(0);
});

it('casts the value to int', function () {
    $calculator = new FixedPointsCalculator;

    expect($calculator->calculate(['points' => '250']))->toBe(250);
});
