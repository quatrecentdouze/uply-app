<?php

declare(strict_types=1);

namespace App\Monitoring\Enums;

enum FlappingTurn: string
{
    case None = 'none';
    case Started = 'started';
    case Ended = 'ended';
}
