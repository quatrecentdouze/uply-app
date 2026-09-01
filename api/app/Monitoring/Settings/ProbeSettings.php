<?php

declare(strict_types=1);

namespace App\Monitoring\Settings;

final readonly class ProbeSettings
{
    public function __construct(
        public int $defaultTimeoutSeconds,
        public int $maxTimeoutSeconds,
        public int $connectTimeoutSeconds,
        public int $maxRedirects,
        public int $bodyReadCapBytes,
        public string $userAgent,
        public bool $allowPrivateTargets,
    ) {}
}
