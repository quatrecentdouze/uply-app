<?php

declare(strict_types=1);

namespace App\Monitoring\Events;

use App\Monitoring\Enums\CheckOutcome;
use App\Monitoring\Enums\FailureKind;
use App\Monitoring\Enums\MonitorState;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class CheckCompletedEvent implements ShouldDispatchAfterCommit
{
    public function __construct(
        public string $monitorId,
        public string $organizationId,
        public CarbonImmutable $checkedAt,
        public CheckOutcome $outcome,
        public ?FailureKind $failureKind,
        public ?int $httpStatus,
        public ?int $responseMs,
        public MonitorState $state,
        public MonitorState $previousState,
        public bool $inMaintenance,
    ) {}
}
