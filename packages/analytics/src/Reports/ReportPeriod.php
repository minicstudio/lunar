<?php

namespace Lunar\Analytics\Reports;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;

class ReportPeriod
{
    /**
     * Longest period, in days, whose figures over time are shown per day.
     */
    public const DAILY_MAX_DAYS = 31;

    /**
     * Longest period, in days, whose figures over time are shown per week.
     */
    public const WEEKLY_MAX_DAYS = 182;

    protected CarbonImmutable $from;

    protected CarbonImmutable $to;

    /**
     * A reversed period is swapped.
     *
     * @param  CarbonInterface  $from  First day of the period, included.
     * @param  CarbonInterface  $to  Last day of the period, included.
     */
    public function __construct(CarbonInterface $from, CarbonInterface $to)
    {
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        $this->from = CarbonImmutable::instance($from)->startOfDay();
        $this->to = CarbonImmutable::instance($to)->endOfDay();
    }

    /**
     * The period's first moment.
     */
    public function from(): CarbonImmutable
    {
        return $this->from;
    }

    /**
     * The period's last moment.
     */
    public function to(): CarbonImmutable
    {
        return $this->to;
    }

    /**
     * Every day of the period, as `Y-m-d`.
     *
     * @return list<string>
     */
    public function days(): array
    {
        $days = [];

        foreach (CarbonPeriod::create($this->from, '1 day', $this->to->startOfDay()) as $day) {
            $days[] = $day->format('Y-m-d');
        }

        return $days;
    }

    /**
     * The interval figures over time are grouped by for this period's length.
     *
     * @return 'day'|'week'|'month'
     */
    public function interval(): string
    {
        $days = (int) round($this->from->diffInDays($this->to->startOfDay())) + 1;

        return match (true) {
            $days <= self::DAILY_MAX_DAYS => 'day',
            $days <= self::WEEKLY_MAX_DAYS => 'week',
            default => 'month',
        };
    }

    /**
     * Sum per-day figures per bucket of `interval()`.
     *
     * Each bucket is keyed by its first day inside the period (`Y-m-d`), so
     * the first and last week or month can be partial. Every bucket of the
     * period is returned; days missing from `$perDay` give 0.
     *
     * @param  array<string, int>  $perDay  Figures keyed `Y-m-d`.
     * @return array<string, int>
     */
    public function group(array $perDay): array
    {
        $interval = $this->interval();
        $buckets = [];

        foreach ($this->days() as $day) {
            $key = $day;

            if ($interval !== 'day') {
                $date = CarbonImmutable::parse($day);
                $bucketStart = $interval === 'week' ? $date->startOfWeek(CarbonInterface::MONDAY) : $date->startOfMonth();
                $key = ($bucketStart->lessThan($this->from) ? $this->from : $bucketStart)->format('Y-m-d');
            }

            $buckets[$key] = ($buckets[$key] ?? 0) + (int) ($perDay[$day] ?? 0);
        }

        return $buckets;
    }
}
