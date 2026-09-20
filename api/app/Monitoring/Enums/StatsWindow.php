<?php

declare(strict_types=1);

namespace App\Monitoring\Enums;

enum StatsWindow: string
{
    case Day = '24h';
    case Week = '7d';
    case Month = '30d';

    public function bucketHours(): int
    {
        return match ($this) {
            self::Day => 1,
            self::Week => 6,
            self::Month => 24,
        };
    }

    public function bucketCount(): int
    {
        return match ($this) {
            self::Day => 24,
            self::Week => 28,
            self::Month => 30,
        };
    }
}
