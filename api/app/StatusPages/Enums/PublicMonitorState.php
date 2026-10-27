<?php

declare(strict_types=1);

namespace App\StatusPages\Enums;

use App\Monitoring\Contracts\MonitorStatus;

enum PublicMonitorState: string
{
    case Up = 'up';
    case Down = 'down';
    case Pending = 'pending';
    case Paused = 'paused';
    case Maintenance = 'maintenance';

    public static function of(MonitorStatus $status, bool $inMaintenance): self
    {
        return $inMaintenance ? self::Maintenance : self::from($status->value);
    }

    public function isWatched(): bool
    {
        return $this !== self::Paused && $this !== self::Maintenance;
    }
}
