<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

use Carbon\CarbonImmutable;

final readonly class MonitorChangePlan
{
    public function __construct(
        public bool $clearsStreak,
        public CarbonImmutable $nextCheckAt,
        public bool $forgetsCertificate = false,
    ) {}
}
