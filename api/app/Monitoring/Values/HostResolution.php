<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

final readonly class HostResolution
{
    public function __construct(
        public array $addresses,
        public int $elapsedMs,
    ) {}
}
