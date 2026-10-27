<?php

declare(strict_types=1);

namespace App\Incidents\Contracts;

use Carbon\CarbonImmutable;

final readonly class OpenIncident
{
    public function __construct(
        public string $id,
        public string $organizationId,
        public string $monitorId,
        public CarbonImmutable $startedAt,
        public ?string $causeFailureKind,
        public ?int $causeHttpStatus,
    ) {}
}
