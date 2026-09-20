<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

use Carbon\CarbonImmutable;

final readonly class SeriesPoint
{
    public function __construct(
        public CarbonImmutable $start,
        public ?float $uptimePercent,
        public ?int $avgResponseMs,
    ) {}
}
