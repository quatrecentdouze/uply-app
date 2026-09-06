<?php

declare(strict_types=1);

namespace App\Alerting\Enums;

enum DeliveryKind: string
{
    case Down = 'down';
    case Up = 'up';
    case Test = 'test';
}
