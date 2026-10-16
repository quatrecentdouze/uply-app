<?php

declare(strict_types=1);

namespace App\Monitoring\Settings;

use App\Monitoring\Values\ConfirmationThresholds;

final readonly class ConfirmationSettings
{
    public function __construct(
        public ConfirmationThresholds $thresholds,
        public int $minThreshold,
        public int $maxThreshold,
        public int $recheckDelaySeconds,
    ) {}

    public function allowsThreshold(int $threshold): bool
    {
        return $threshold >= $this->minThreshold && $threshold <= $this->maxThreshold;
    }
}
