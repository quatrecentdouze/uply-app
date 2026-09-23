<?php

declare(strict_types=1);

namespace App\Incidents\Contracts;

use Carbon\CarbonImmutable;

interface IncidentHistory
{
    public function since(array $monitorIds, CarbonImmutable $since, int $limit): array;
}
