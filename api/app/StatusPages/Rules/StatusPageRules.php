<?php

declare(strict_types=1);

namespace App\StatusPages\Rules;

use App\StatusPages\Enums\OverallState;
use App\StatusPages\Enums\PublicMonitorState;
use Carbon\CarbonImmutable;

final class StatusPageRules
{
    public static function displayName(?string $given, string $monitorName, int $maxLength): string
    {
        $name = $given !== null && trim($given) !== '' ? trim($given) : trim($monitorName);

        return mb_substr($name, 0, $maxLength);
    }

    public static function overallState(array $states): OverallState
    {
        $watched = 0;
        $down = 0;
        foreach ($states as $state) {
            if (! $state instanceof PublicMonitorState || ! $state->isWatched()) {
                continue;
            }
            $watched++;
            $down += $state === PublicMonitorState::Down ? 1 : 0;
        }

        return match (true) {
            $down === 0 => OverallState::Operational,
            $down === $watched => OverallState::MajorOutage,
            default => OverallState::PartialOutage,
        };
    }

    public static function incidentsSince(CarbonImmutable $now, int $days): CarbonImmutable
    {
        return $now->utc()->subDays($days);
    }
}
