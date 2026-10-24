<?php

declare(strict_types=1);

namespace App\Maintenance\Enums;

enum PeriodProblem: string
{
    case StartInPast = 'start_in_past';
    case StartTooFar = 'start_too_far';
    case EndNotAfterStart = 'end_not_after_start';
    case EndInPast = 'end_in_past';
    case TooLong = 'too_long';

    public function field(): string
    {
        return match ($this) {
            self::StartInPast, self::StartTooFar => 'starts_at',
            self::EndNotAfterStart, self::EndInPast, self::TooLong => 'ends_at',
        };
    }
}
