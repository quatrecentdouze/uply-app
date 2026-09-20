<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

use App\Monitoring\Enums\CheckOutcome;
use App\Monitoring\Enums\FailureKind;
use Carbon\CarbonImmutable;

final readonly class CheckRecord
{
    public function __construct(
        public CarbonImmutable $checkedAt,
        public CheckOutcome $outcome,
        public ?FailureKind $failureKind,
        public ?int $httpStatus,
        public ?int $responseMs,
        public ProbeTimings $timings,
        public ?string $errorMessage,
    ) {}
}
