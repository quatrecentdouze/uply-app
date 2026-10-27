<?php

declare(strict_types=1);

namespace App\Monitoring\Rules;

use App\Monitoring\Contracts\DailyUptime;
use App\Monitoring\Contracts\UptimeHistory;
use App\Monitoring\Values\CheckTotals;
use Carbon\CarbonImmutable;

final class UptimeRules
{
    private const int HUNDREDTHS_OF_PERCENT = 10000;

    private const int HUNDREDTHS = 100;

    private const string DAY_KEY = 'Y-m-d';

    public static function percent(int $checksUp, int $checksTotal): ?float
    {
        if ($checksTotal <= 0) {
            return null;
        }

        return intdiv(min($checksUp, $checksTotal) * self::HUNDREDTHS_OF_PERCENT, $checksTotal) / self::HUNDREDTHS;
    }

    public static function rollingDayStart(CarbonImmutable $now): CarbonImmutable
    {
        return $now->utc()->subDay();
    }

    public static function firstDay(CarbonImmutable $lastDay, int $days): CarbonImmutable
    {
        return $lastDay->utc()->startOfDay()->subDays(max(1, $days) - 1);
    }

    public static function history(CarbonImmutable $lastDay, int $days, array $totalsByDay, array $maintenanceDays): UptimeHistory
    {
        $first = self::firstDay($lastDay, $days);
        $series = [];
        $up = 0;
        $total = 0;
        for ($offset = 0; $offset < max(1, $days); $offset++) {
            $day = $first->addDays($offset);
            $key = $day->format(self::DAY_KEY);
            $totals = $totalsByDay[$key] ?? null;
            $dayTotals = $totals instanceof CheckTotals ? $totals : CheckTotals::none();
            $up += $dayTotals->checksUp;
            $total += $dayTotals->checksTotal;
            $series[] = new DailyUptime($day, self::percent($dayTotals->checksUp, $dayTotals->checksTotal), in_array($key, $maintenanceDays, true));
        }

        return new UptimeHistory(self::percent($up, $total), $series);
    }

    public static function averageMs(int $sum, int $count): ?int
    {
        return $count <= 0 ? null : (int) round($sum / $count);
    }
}
