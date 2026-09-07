<?php

declare(strict_types=1);

namespace App\Alerting\Values;

final readonly class SendReport
{
    private function __construct(
        public bool $accepted,
        public ?int $httpStatus,
        public ?int $retryAfterSeconds,
        public string $diagnostic,
    ) {}

    public static function accepted(?int $httpStatus = null): self
    {
        return new self(true, $httpStatus, null, '');
    }

    public static function responded(int $httpStatus, ?int $retryAfterSeconds, string $diagnostic): self
    {
        return new self(false, $httpStatus, $retryAfterSeconds, $diagnostic);
    }

    public static function transportFailed(string $diagnostic): self
    {
        return new self(false, null, null, $diagnostic);
    }
}
