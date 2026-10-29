<?php

declare(strict_types=1);

namespace App\Monitoring\Events;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class MonitorFlappingStartedEvent implements ShouldDispatchAfterCommit
{
    public function __construct(
        public string $monitorId,
        public string $organizationId,
        public CarbonImmutable $flappingSince,
        public int $changes,
        public int $windowMinutes,
    ) {}
}
