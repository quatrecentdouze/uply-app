<?php

declare(strict_types=1);

namespace App\Monitoring\Contracts;

final readonly class MonitorView
{
    public function __construct(
        public string $id,
        public string $organizationId,
        public string $name,
        public string $url,
        public MonitorStatus $status,
    ) {}
}
