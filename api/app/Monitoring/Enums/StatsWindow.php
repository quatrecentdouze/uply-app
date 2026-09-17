<?php

declare(strict_types=1);

namespace App\Monitoring\Enums;

enum StatsWindow: string
{
    case Day = '24h';
    case Week = '7d';
    case Month = '30d';
}
