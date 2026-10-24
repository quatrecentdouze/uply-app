<?php

declare(strict_types=1);

namespace App\Maintenance\Contracts;

use Carbon\CarbonImmutable;

final readonly class ScheduledWindow
{
    public function __construct(
        public string $id,
        public string $name,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public bool $isActive,
    ) {}
}
