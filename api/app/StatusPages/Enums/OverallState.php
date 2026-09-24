<?php

declare(strict_types=1);

namespace App\StatusPages\Enums;

enum OverallState: string
{
    case Operational = 'operational';
    case PartialOutage = 'partial_outage';
    case MajorOutage = 'major_outage';
}
