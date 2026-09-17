<?php

declare(strict_types=1);

namespace App\Monitoring\Enums;

use App\Shared\Errors\ErrorCode;

enum MonitoringError: string implements ErrorCode
{
    case MonitorLimitReached = 'monitor_limit_reached';

    public function code(): string
    {
        return $this->value;
    }

    public function httpStatus(): int
    {
        return 409;
    }

    public function messageKey(): string
    {
        return 'monitoring.errors.'.$this->value;
    }
}
