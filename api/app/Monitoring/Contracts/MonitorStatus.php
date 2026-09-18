<?php

declare(strict_types=1);

namespace App\Monitoring\Contracts;

use App\Monitoring\Enums\MonitorState;

enum MonitorStatus: string
{
    case Up = 'up';
    case Down = 'down';
    case Pending = 'pending';
    case Paused = 'paused';

    public static function of(MonitorState $state, bool $isPaused): self
    {
        return $isPaused ? self::Paused : self::from($state->value);
    }
}
