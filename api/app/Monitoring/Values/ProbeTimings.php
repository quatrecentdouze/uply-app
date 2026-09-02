<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

use InvalidArgumentException;

final readonly class ProbeTimings
{
    public function __construct(
        public ?int $dnsMs = null,
        public ?int $connectMs = null,
        public ?int $tlsMs = null,
        public ?int $ttfbMs = null,
        public ?int $totalMs = null,
    ) {
        foreach ([$dnsMs, $connectMs, $tlsMs, $ttfbMs, $totalMs] as $milliseconds) {
            if ($milliseconds !== null && $milliseconds < 0) {
                throw new InvalidArgumentException('probe timings cannot be negative');
            }
        }
    }

    public static function none(): self
    {
        return new self;
    }
}
