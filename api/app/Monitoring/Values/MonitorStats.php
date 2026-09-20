<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

use App\Monitoring\Enums\StatsWindow;

final readonly class MonitorStats
{
    public function __construct(
        public StatsWindow $window,
        public StatsSummary $summary,
        public array $series,
        public ProbeTimings $timings,
    ) {}
}
