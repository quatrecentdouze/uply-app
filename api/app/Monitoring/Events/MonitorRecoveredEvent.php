<?php

declare(strict_types=1);

namespace App\Monitoring\Events;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class MonitorRecoveredEvent implements ShouldDispatchAfterCommit
{
    public function __construct(
        public string $monitorId,
        public string $organizationId,
        public CarbonImmutable $upSince,
        public CarbonImmutable $confirmedAt,
    ) {}
}
