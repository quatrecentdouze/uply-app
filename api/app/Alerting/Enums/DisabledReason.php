<?php

declare(strict_types=1);

namespace App\Alerting\Enums;

enum DisabledReason: string
{
    case WebhookRejected = 'webhook_rejected';
}
