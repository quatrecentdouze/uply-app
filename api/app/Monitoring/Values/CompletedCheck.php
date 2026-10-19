<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

use Carbon\CarbonImmutable;

final readonly class CompletedCheck
{
    public function __construct(
        public string $monitorId,
        public CarbonImmutable $scheduledAt,
        public CarbonImmutable $checkedAt,
        public ProbeResult $result,
        public CheckVerdict $verdict,
    ) {}

    public function errorMessage(): ?string
    {
        return $this->verdict->diagnostic ?? $this->result->errorMessage;
    }
}
