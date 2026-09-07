<?php

declare(strict_types=1);

namespace App\Alerting\Values;

final readonly class SendReport
{
    private function __construct(
        public bool $accepted,
        public bool $permanent,
        public ?int $httpStatus,
        public ?int $retryAfterSeconds,
        public string $diagnostic,
    ) {}

    public static function accepted(?int $httpStatus = null): self
    {
        return new self(true, false, $httpStatus, null, '');
    }

    public static function responded(int $httpStatus, ?int $retryAfterSeconds, string $diagnostic): self
    {
        return new self(false, false, $httpStatus, $retryAfterSeconds, $diagnostic);
    }

    public static function transportFailed(string $diagnostic): self
    {
        return new self(false, false, null, null, $diagnostic);
    }

    public static function refused(string $diagnostic): self
    {
        return new self(false, true, null, null, $diagnostic);
    }
}
