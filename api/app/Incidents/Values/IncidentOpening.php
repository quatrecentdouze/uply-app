<?php

declare(strict_types=1);

namespace App\Incidents\Values;

use Carbon\CarbonImmutable;

final readonly class IncidentOpening
{
    public function __construct(
        public string $organizationId,
        public string $monitorId,
        public CarbonImmutable $startedAt,
        public string $causeFailureKind,
        public ?int $causeHttpStatus,
        public ?string $causeMessage,
        public bool $monitorFlapping,
    ) {}
}
