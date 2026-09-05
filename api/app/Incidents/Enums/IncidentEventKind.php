<?php

declare(strict_types=1);

namespace App\Incidents\Enums;

enum IncidentEventKind: string
{
    case Opened = 'opened';
    case Resolved = 'resolved';
    case AlertSent = 'alert_sent';
    case AlertFailed = 'alert_failed';
}
