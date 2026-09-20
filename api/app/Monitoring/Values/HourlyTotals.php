<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

use Carbon\CarbonImmutable;

final readonly class HourlyTotals
{
    public function __construct(
        public CarbonImmutable $hourStart,
        public CheckTotals $totals,
    ) {}
}
