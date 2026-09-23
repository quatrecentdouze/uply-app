<?php

declare(strict_types=1);

namespace App\Incidents\Contracts;

use Carbon\CarbonImmutable;

final readonly class IncidentSummary
{
    public function __construct(
        public string $id,
        public string $monitorId,
        public CarbonImmutable $startedAt,
        public ?CarbonImmutable $resolvedAt,
        public ?int $durationSeconds,
    ) {}
}
