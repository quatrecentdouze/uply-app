<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

final readonly class PinnedTarget
{
    public function __construct(
        public TargetUrl $target,
        public string $address,
        public ?int $dnsMs,
    ) {}
}
