<?php

use Lunar\Loyalty\Calculators\OrderTotalCalculator;

uses(\Lunar\Tests\Loyalty\TestCase::class);

it('calculates points from order total minor units', function (int $total, int $ratio, int $expected) {
    config(['lunar.loyalty.currency.earn_ratio' => $ratio]);

    $calculator = new OrderTotalCalculator;

    expect($calculator->calculate(['order_total_minor' => $total]))->toBe($expected);
})->with([
    [10000, 100, 100],
    [9999, 100, 99],
    [500, 100, 5],
    [0, 100, 0],
]);
