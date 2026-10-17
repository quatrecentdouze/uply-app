<?php

declare(strict_types=1);

namespace App\Monitoring\Settings;

final readonly class RequestSettings
{
    public function __construct(
        public int $maxHeaders,
        public int $headerNameMaxLength,
        public int $headerValueMaxLength,
        public int $bodyMaxLength,
        public array $refusedHeaderNames,
    ) {}

    public function refusesHeader(string $name): bool
    {
        return in_array(strtolower($name), $this->refusedHeaderNames, true);
    }
}
