<?php

declare(strict_types=1);

namespace App\StatusPages\Settings;

final readonly class PublicPageSettings
{
    public function __construct(
        public int $cacheSeconds,
        public int $barDays,
        public int $incidentDays,
        public int $rateLimitPerMinute,
    ) {}
}
