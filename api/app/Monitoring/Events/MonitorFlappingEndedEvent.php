<?php

declare(strict_types=1);

namespace App\Monitoring\Events;

use App\Monitoring\Enums\MonitorState;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class MonitorFlappingEndedEvent implements ShouldDispatchAfterCommit
{
    public function __construct(
        public string $monitorId,
        public string $organizationId,
        public CarbonImmutable $flappingSince,
        public CarbonImmutable $endedAt,
        public MonitorState $state,
        public int $stableMinutes,
    ) {}
}
