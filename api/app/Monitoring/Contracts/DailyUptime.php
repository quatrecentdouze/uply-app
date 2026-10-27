<?php

declare(strict_types=1);

namespace App\Monitoring\Contracts;

use Carbon\CarbonImmutable;

final readonly class DailyUptime
{
    public function __construct(
        public CarbonImmutable $day,
        public ?float $uptimePercent,
        public bool $maintenance,
    ) {}
}
