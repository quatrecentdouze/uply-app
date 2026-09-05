<?php

declare(strict_types=1);

namespace App\Incidents\Rules;

use Carbon\CarbonImmutable;

final class IncidentRules
{
    public static function resolutionTime(CarbonImmutable $startedAt, CarbonImmutable $recoveredAt): CarbonImmutable
    {
        return $recoveredAt->lessThan($startedAt) ? $startedAt : $recoveredAt;
    }

    public static function durationSeconds(CarbonImmutable $startedAt, CarbonImmutable $resolvedAt): int
    {
        return max(0, $resolvedAt->getTimestamp() - $startedAt->getTimestamp());
    }
}
