<?php

declare(strict_types=1);

namespace App\Alerting\Enums;

enum DecisionOutcome: string
{
    case Sent = 'sent';
    case Retry = 'retry';
    case Failed = 'failed';
}
