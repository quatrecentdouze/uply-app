<?php

declare(strict_types=1);

namespace App\StatusPages\Values;

final readonly class PageMonitorEntry
{
    public function __construct(
        public string $monitorId,
        public ?string $displayName,
    ) {}
}
