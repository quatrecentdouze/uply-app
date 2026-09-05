<?php

declare(strict_types=1);

namespace App\Monitoring\Rules;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class CheckScheduleRules
{
    public static function nextCheckAt(
        CarbonImmutable $scheduledAt,
        CarbonImmutable $checkedAt,
        int $intervalSeconds,
        int $recheckDelaySeconds,
        bool $awaitsConfirmation,
    ): CarbonImmutable {
        if ($intervalSeconds < 1 || $recheckDelaySeconds < 1) {
            throw new InvalidArgumentException('interval and recheck delay must be at least one second');
        }
        $regular = self::nextSlot($scheduledAt, $checkedAt, $intervalSeconds);
        if (! $awaitsConfirmation) {
            return $regular;
        }
        $recheck = $checkedAt->addSeconds($recheckDelaySeconds);

        return $recheck->lessThan($regular) ? $recheck : $regular;
    }

    public static function slotIsOpen(?CarbonImmutable $lastCheckedAt, CarbonImmutable $scheduledAt): bool
    {
        return $lastCheckedAt === null || $lastCheckedAt->lessThan($scheduledAt);
    }

    private static function nextSlot(CarbonImmutable $scheduledAt, CarbonImmutable $checkedAt, int $intervalSeconds): CarbonImmutable
    {
        $late = max(0, $checkedAt->getTimestamp() - $scheduledAt->getTimestamp());
        $slotsPassed = intdiv($late, $intervalSeconds) + 1;

        return $scheduledAt->addSeconds($slotsPassed * $intervalSeconds);
    }
}
