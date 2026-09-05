<?php

declare(strict_types=1);

namespace App\Shared;

enum QueueName: string
{
    case Alerts = 'alerts';
    case Realtime = 'realtime';
    case Checks = 'checks';
    case Default = 'default';
}
