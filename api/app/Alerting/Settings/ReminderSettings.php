<?php

declare(strict_types=1);

namespace App\Alerting\Settings;

final readonly class ReminderSettings
{
    public function __construct(
        public array $intervalsMinutes,
        public int $maxPerIncident,
    ) {}
}
