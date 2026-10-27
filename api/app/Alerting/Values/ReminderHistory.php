<?php

declare(strict_types=1);

namespace App\Alerting\Values;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class ReminderHistory
{
    public function __construct(
        public ?CarbonImmutable $lastAlertAt,
        public int $remindersSent,
    ) {
        if ($remindersSent < 0) {
            throw new InvalidArgumentException('a channel cannot have sent fewer than zero reminders');
        }
    }

    public static function none(): self
    {
        return new self(null, 0);
    }
}
