<?php

declare(strict_types=1);

namespace App\Monitoring\Settings;

final readonly class MonitorLimits
{
    public function __construct(
        public int $maxMonitorsPerOrganization,
        public int $nameMaxLength,
        public int $urlMaxLength,
        public int $maxAcceptedStatusRanges,
        public int $keywordMaxLength,
    ) {}
}
