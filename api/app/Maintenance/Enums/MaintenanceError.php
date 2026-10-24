<?php

declare(strict_types=1);

namespace App\Maintenance\Enums;

use App\Shared\Errors\ErrorCode;

enum MaintenanceError: string implements ErrorCode
{
    case MaintenanceStarted = 'maintenance_started';
    case MaintenanceEnded = 'maintenance_ended';
    case MaintenanceNotActive = 'maintenance_not_active';
    case MaintenanceLimitReached = 'maintenance_limit_reached';

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
        return 'maintenance.errors.'.$this->value;
    }
}
