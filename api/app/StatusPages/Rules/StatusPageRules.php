<?php

declare(strict_types=1);

namespace App\StatusPages\Rules;

use App\Monitoring\Contracts\MonitorStatus;
use App\StatusPages\Enums\OverallState;
use Carbon\CarbonImmutable;

final class StatusPageRules
{
    public static function displayName(?string $given, string $monitorName, int $maxLength): string
    {
        $name = $given !== null && trim($given) !== '' ? trim($given) : trim($monitorName);

        return mb_substr($name, 0, $maxLength);
    }

    public static function overallState(array $statuses): OverallState
    {
        $watched = 0;
        $down = 0;
        foreach ($statuses as $status) {
            if (! $status instanceof MonitorStatus || $status === MonitorStatus::Paused) {
                continue;
            }
            $watched++;
            $down += $status === MonitorStatus::Down ? 1 : 0;
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
