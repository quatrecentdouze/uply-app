<?php

declare(strict_types=1);

namespace App\Monitoring\Rules;

final class UptimeRules
{
    private const int HUNDREDTHS_OF_PERCENT = 10000;

    private const int HUNDREDTHS = 100;

    public static function percent(int $checksUp, int $checksTotal): ?float
    {
        if ($checksTotal <= 0) {
            return null;
        }

        return intdiv(min($checksUp, $checksTotal) * self::HUNDREDTHS_OF_PERCENT, $checksTotal) / self::HUNDREDTHS;
    }

    public static function averageMs(int $sum, int $count): ?int
    {
        return $count <= 0 ? null : (int) round($sum / $count);
    }
}
