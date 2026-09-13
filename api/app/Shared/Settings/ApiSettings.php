<?php

declare(strict_types=1);

namespace App\Shared\Settings;

final readonly class ApiSettings
{
    public function __construct(
        public int $rateLimitPerMinute,
        public int $metaCacheSeconds,
        public PaginationSettings $pagination,
    ) {}
}
