<?php

declare(strict_types=1);

namespace App\Monitoring\Enums;

enum StateTransition: string
{
    case None = 'none';
    case WentDown = 'went_down';
    case Recovered = 'recovered';
}
