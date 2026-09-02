<?php

declare(strict_types=1);

namespace App\Monitoring\Enums;

enum CheckOutcome: string
{
    case Up = 'up';
    case Down = 'down';
}
