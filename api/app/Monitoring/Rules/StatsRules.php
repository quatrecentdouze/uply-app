<?php

declare(strict_types=1);

namespace App\Monitoring\Rules;

use App\Monitoring\Enums\StatsWindow;
use App\Monitoring\Values\BucketPlan;
use App\Monitoring\Values\CheckTotals;
use App\Monitoring\Values\HourlyTotals;
use App\Monitoring\Values\SeriesPoint;
use App\Monitoring\Values\StatsSummary;
use Carbon\CarbonImmutable;

final class StatsRules
{
    public static function plan(StatsWindow $window, CarbonImmutable $now): BucketPlan
    {
        $hours = $window->bucketHours();
        $currentHour = $now->utc()->startOfHour();
        $lastStart = $currentHour->setTime(intdiv($currentHour->hour, $hours) * $hours, 0);

        return new BucketPlan($lastStart->subHours($hours * ($window->bucketCount() - 1)), $hours, $window->bucketCount());
    }

    public static function series(BucketPlan $plan, array $hours): array
    {
        $buckets = array_fill(0, $plan->count, []);
        foreach ($hours as $hour) {
            if (! $hour instanceof HourlyTotals) {
                continue;
            }
            $index = $plan->indexOf($hour->hourStart->utc());
            if ($index !== null) {
                $buckets[$index][] = $hour->totals;
            }
        }

        $series = [];
        foreach ($buckets as $index => $totals) {
            $combined = self::combine($totals);
            $series[] = new SeriesPoint(
                $plan->start($index),
                UptimeRules::percent($combined->checksUp, $combined->checksTotal),
                UptimeRules::averageMs($combined->responseMsSum, $combined->responseMsCount),
            );
        }

        return $series;
    }

    public static function within(BucketPlan $plan, array $hours): CheckTotals
    {
        $totals = [];
        foreach ($hours as $hour) {
            if ($hour instanceof HourlyTotals && $plan->indexOf($hour->hourStart->utc()) !== null) {
                $totals[] = $hour->totals;
            }
        }

        return self::combine($totals);
    }

    public static function combine(array $totals): CheckTotals
    {
        $checksTotal = 0;
        $checksUp = 0;
        $responseMsSum = 0;
        $responseMsCount = 0;
        $min = null;
        $max = null;
        foreach ($totals as $part) {
            if (! $part instanceof CheckTotals) {
                continue;
            }
            $checksTotal += $part->checksTotal;
            $checksUp += $part->checksUp;
            $responseMsSum += $part->responseMsSum;
            $responseMsCount += $part->responseMsCount;
            $min = $part->responseMsMin === null ? $min : min($min ?? $part->responseMsMin, $part->responseMsMin);
            $max = $part->responseMsMax === null ? $max : max($max ?? $part->responseMsMax, $part->responseMsMax);
        }

        return new CheckTotals($checksTotal, $checksUp, $responseMsSum, $responseMsCount, $min, $max);
    }

    public static function summary(CheckTotals $totals): StatsSummary
    {
        return new StatsSummary(
            $totals->checksTotal,
            UptimeRules::percent($totals->checksUp, $totals->checksTotal),
            UptimeRules::averageMs($totals->responseMsSum, $totals->responseMsCount),
            $totals->responseMsMin,
            $totals->responseMsMax,
        );
    }
}
