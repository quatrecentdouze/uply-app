<?php

declare(strict_types=1);

namespace App\Monitoring\Enums;

enum CheckOutcome: string
{
    case Up = 'up';
    case Down = 'down';

    public function confirmedState(): MonitorState
    {
        return match ($this) {
            self::Up => MonitorState::Up,
            self::Down => MonitorState::Down,
        };
    }
}
