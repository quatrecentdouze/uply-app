<?php

declare(strict_types=1);

namespace App\Monitoring\Contracts;

use Carbon\CarbonImmutable;

interface UptimeQuery
{
    public function daily(array $monitorIds, CarbonImmutable $lastDay, int $days): array;
}
