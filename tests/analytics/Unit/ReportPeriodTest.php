<?php

use Carbon\CarbonImmutable;
use Lunar\Analytics\Reports\ReportPeriod;
use Lunar\Tests\Analytics\TestCase;

uses(TestCase::class);

function reportPeriod(string $from, string $to): ReportPeriod
{
    return new ReportPeriod(CarbonImmutable::parse($from), CarbonImmutable::parse($to));
}

test('a period covers whole days and a reversed period is swapped', function () {
    $period = reportPeriod('2026-10-07 15:30', '2026-10-01 09:00');

    expect($period->from()->toDateTimeString())->toBe('2026-10-01 00:00:00')
        ->and($period->to()->toDateTimeString())->toBe('2026-10-07 23:59:59')
        ->and($period->days())->toBe([
            '2026-10-01', '2026-10-02', '2026-10-03', '2026-10-04', '2026-10-05', '2026-10-06', '2026-10-07',
        ]);
});

test('the interval follows the period length', function (int $days, string $interval) {
    $from = CarbonImmutable::parse('2026-01-01');

    expect((new ReportPeriod($from, $from->addDays($days - 1)))->interval())->toBe($interval);
})->with([
    '31 days' => [31, 'day'],
    '32 days' => [32, 'week'],
    '182 days' => [182, 'week'],
    '183 days' => [183, 'month'],
]);

test('days are kept as they are and a missing day gives 0', function () {
    expect(reportPeriod('2026-10-01', '2026-10-03')->group(['2026-10-01' => 2, '2026-10-03' => 5]))->toBe([
        '2026-10-01' => 2,
        '2026-10-02' => 0,
        '2026-10-03' => 5,
    ]);
});

test('weeks start on Monday and a partial first week is keyed by the period start', function () {
    $weeks = reportPeriod('2026-08-04', '2026-11-08')->group([
        '2026-08-03' => 100,
        '2026-08-04' => 2,
        '2026-08-09' => 1,
        '2026-08-10' => 4,
        '2026-11-08' => 5,
    ]);

    expect($weeks)->toHaveCount(14)
        ->and(array_key_first($weeks))->toBe('2026-08-04')
        ->and(array_keys($weeks)[1])->toBe('2026-08-10')
        ->and(array_key_last($weeks))->toBe('2026-11-02')
        ->and($weeks['2026-08-04'])->toBe(3)
        ->and($weeks['2026-08-10'])->toBe(4)
        ->and($weeks['2026-08-17'])->toBe(0)
        ->and($weeks['2026-11-02'])->toBe(5);
});

test('months are calendar months and a partial first month is keyed by the period start', function () {
    $months = reportPeriod('2026-01-15', '2026-12-31')->group([
        '2026-01-20' => 1,
        '2026-02-01' => 2,
        '2026-02-28' => 3,
    ]);

    expect(array_keys($months))->toBe([
        '2026-01-15', '2026-02-01', '2026-03-01', '2026-04-01', '2026-05-01', '2026-06-01',
        '2026-07-01', '2026-08-01', '2026-09-01', '2026-10-01', '2026-11-01', '2026-12-01',
    ])->and($months['2026-01-15'])->toBe(1)
        ->and($months['2026-02-01'])->toBe(5)
        ->and($months['2026-03-01'])->toBe(0);
});
