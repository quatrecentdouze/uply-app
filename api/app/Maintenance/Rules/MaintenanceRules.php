<?php

declare(strict_types=1);

namespace App\Maintenance\Rules;

use App\Maintenance\Enums\MaintenanceState;
use App\Maintenance\Enums\PeriodProblem;
use Carbon\CarbonImmutable;

final class MaintenanceRules
{
    private const int SHORTEST_WINDOW_SECONDS = 1;

    public static function state(CarbonImmutable $startsAt, CarbonImmutable $endsAt, CarbonImmutable $now): MaintenanceState
    {
        return match (true) {
            $now->lessThan($startsAt) => MaintenanceState::Upcoming,
            $now->lessThan($endsAt) => MaintenanceState::Active,
            default => MaintenanceState::Ended,
        };
    }

    public static function covers(CarbonImmutable $startsAt, CarbonImmutable $endsAt, CarbonImmutable $at): bool
    {
        return self::state($startsAt, $endsAt, $at) === MaintenanceState::Active;
    }

    public static function moment(CarbonImmutable $time): CarbonImmutable
    {
        return $time->utc()->startOfSecond();
    }

    public static function endedAt(CarbonImmutable $startsAt, CarbonImmutable $now): CarbonImmutable
    {
        return self::moment($now)->max($startsAt->addSeconds(self::SHORTEST_WINDOW_SECONDS));
    }

    public static function periodProblems(
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        CarbonImmutable $now,
        bool $checksStart,
        int $startGraceSeconds,
        int $maxDaysAhead,
        int $maxDurationHours,
    ): array {
        $problems = [];
        if ($checksStart && $startsAt->lessThan($now->subSeconds($startGraceSeconds))) {
            $problems[] = PeriodProblem::StartInPast;
        }
        if ($checksStart && $startsAt->greaterThan($now->addDays($maxDaysAhead))) {
            $problems[] = PeriodProblem::StartTooFar;
        }
        if (! $endsAt->greaterThan($startsAt)) {
            $problems[] = PeriodProblem::EndNotAfterStart;
        } elseif (! $endsAt->greaterThan($now)) {
            $problems[] = PeriodProblem::EndInPast;
        } elseif ($endsAt->greaterThan($startsAt->addHours($maxDurationHours))) {
            $problems[] = PeriodProblem::TooLong;
        }

        return $problems;
    }
}
