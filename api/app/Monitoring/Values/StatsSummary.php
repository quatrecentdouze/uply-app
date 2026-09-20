<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

final readonly class StatsSummary
{
    public function __construct(
        public int $checksTotal,
        public ?float $uptimePercent,
        public ?int $avgResponseMs,
        public ?int $minResponseMs,
        public ?int $maxResponseMs,
    ) {}
}
