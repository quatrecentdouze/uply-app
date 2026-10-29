<?php

declare(strict_types=1);

namespace App\Monitoring\Events;

use App\Monitoring\Enums\FailureKind;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class MonitorWentDownEvent implements ShouldDispatchAfterCommit
{
    public function __construct(
        public string $monitorId,
        public string $organizationId,
        public CarbonImmutable $downSince,
        public CarbonImmutable $confirmedAt,
        public FailureKind $failureKind,
        public ?int $httpStatus,
        public ?string $errorMessage,
        public bool $flapping,
    ) {}
}
