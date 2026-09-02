<?php

declare(strict_types=1);

namespace App\Monitoring\Enums;

enum MonitorState: string
{
    case Pending = 'pending';
    case Up = 'up';
    case Down = 'down';
}
