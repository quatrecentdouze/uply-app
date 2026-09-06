<?php

declare(strict_types=1);

namespace App\Alerting\Enums;

enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
}
