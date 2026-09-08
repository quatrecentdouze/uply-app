<?php

declare(strict_types=1);

namespace App\Alerting\Enums;

enum TestAlertStatus: string
{
    case Queued = 'queued';
    case Limited = 'limited';
    case ChannelNotFound = 'channel_not_found';
}
