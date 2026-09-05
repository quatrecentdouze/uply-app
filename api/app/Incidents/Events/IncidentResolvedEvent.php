<?php

declare(strict_types=1);

namespace App\Incidents\Events;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class IncidentResolvedEvent implements ShouldDispatchAfterCommit
{
    public function __construct(
        public string $incidentId,
        public string $organizationId,
        public string $monitorId,
        public CarbonImmutable $startedAt,
        public CarbonImmutable $resolvedAt,
        public int $durationSeconds,
    ) {}
}
