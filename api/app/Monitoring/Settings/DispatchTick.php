<?php

declare(strict_types=1);

namespace App\Monitoring\Settings;

enum DispatchTick: int
{
    case EverySecond = 1;
    case EveryTwoSeconds = 2;
    case EveryFiveSeconds = 5;
    case EveryTenSeconds = 10;
    case EveryFifteenSeconds = 15;
    case EveryTwentySeconds = 20;
    case EveryThirtySeconds = 30;
}
