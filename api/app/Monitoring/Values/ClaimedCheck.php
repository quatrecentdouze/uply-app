<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

use Carbon\CarbonImmutable;

final readonly class ClaimedCheck
{
    public function __construct(
        public string $monitorId,
        public CarbonImmutable $scheduledAt,
    ) {}
}
