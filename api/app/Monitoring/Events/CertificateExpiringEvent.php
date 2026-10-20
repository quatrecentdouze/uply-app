<?php

declare(strict_types=1);

namespace App\Monitoring\Events;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class CertificateExpiringEvent implements ShouldDispatchAfterCommit
{
    public function __construct(
        public string $monitorId,
        public string $organizationId,
        public CarbonImmutable $expiresAt,
        public int $days,
        public CarbonImmutable $detectedAt,
    ) {}
}
