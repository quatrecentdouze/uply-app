<?php

declare(strict_types=1);

namespace App\Maintenance\Enums;

enum MaintenanceState: string
{
    case Upcoming = 'upcoming';
    case Active = 'active';
    case Ended = 'ended';
}
