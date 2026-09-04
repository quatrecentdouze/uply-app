<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

final readonly class TargetUrl
{
    public function __construct(
        public string $url,
        public string $scheme,
        public string $host,
        public int $port,
        public bool $hostIsAddress,
    ) {}
}
