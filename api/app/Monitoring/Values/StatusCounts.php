<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

use App\Monitoring\Contracts\MonitorStatus;

final readonly class StatusCounts
{
    public function __construct(private array $counts) {}

    public function of(MonitorStatus $status): int
    {
        $count = $this->counts[$status->value] ?? 0;

        return is_int($count) ? $count : 0;
    }
}
