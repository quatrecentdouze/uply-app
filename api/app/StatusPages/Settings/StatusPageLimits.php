<?php

declare(strict_types=1);

namespace App\StatusPages\Settings;

final readonly class StatusPageLimits
{
    public function __construct(
        public int $maxPagesPerOrganization,
        public int $maxMonitorsPerPage,
    ) {}
}
