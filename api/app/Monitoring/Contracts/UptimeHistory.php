<?php

declare(strict_types=1);

namespace App\Monitoring\Contracts;

final readonly class UptimeHistory
{
    public function __construct(
        public ?float $uptimePercent,
        public array $days,
    ) {}
}
