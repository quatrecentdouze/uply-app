<?php

declare(strict_types=1);

namespace App\Incidents\Enums;

enum IncidentState: string
{
    case Open = 'open';
    case Resolved = 'resolved';
}
