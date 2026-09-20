<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

use App\Monitoring\Enums\StatsWindow;

final readonly class OrganizationOverview
{
    public function __construct(
        public StatsWindow $window,
        public StatusCounts $counts,
        public StatsSummary $summary,
        public array $series,
    ) {}
}
