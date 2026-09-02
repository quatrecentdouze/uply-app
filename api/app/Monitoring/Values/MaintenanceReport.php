<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

final readonly class MaintenanceReport
{
    public function __construct(
        public int $partitionsCreated,
        public int $partitionsDropped,
        public int $hourlyStatsPurged,
    ) {}
}
