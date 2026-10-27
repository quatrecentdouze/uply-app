<?php

declare(strict_types=1);

namespace App\Alerting\Rules;

use App\Alerting\Values\ReminderHistory;
use Carbon\CarbonImmutable;

final class ReminderRules
{
    public static function nextNumber(
        ReminderHistory $history,
        CarbonImmutable $openedAt,
        CarbonImmutable $now,
        int $intervalMinutes,
        int $maxReminders,
        bool $silenced,
    ): ?int {
        if ($silenced || $history->remindersSent >= $maxReminders) {
            return null;
        }
        $dueAt = ($history->lastAlertAt ?? $openedAt)->addMinutes($intervalMinutes);

        return $now->lessThan($dueAt) ? null : $history->remindersSent + 1;
    }
}
