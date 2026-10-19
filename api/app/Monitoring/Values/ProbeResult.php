<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

use App\Monitoring\Enums\FailureKind;
use InvalidArgumentException;

final readonly class ProbeResult
{
    public const int MAX_ERROR_MESSAGE_LENGTH = 500;

    public ?string $errorMessage;

    private function __construct(
        public ?int $httpStatus,
        public ?FailureKind $failureKind,
        public ProbeTimings $timings,
        ?string $errorMessage,
        public ?string $body = null,
    ) {
        if ($failureKind === FailureKind::HttpStatus) {
            throw new InvalidArgumentException('a probe reports transport failures, the http status is judged by the rules');
        }
        if ($failureKind === null && $httpStatus === null) {
            throw new InvalidArgumentException('a probe result without failure needs an http status');
        }
        $this->errorMessage = $errorMessage === null ? null : mb_substr($errorMessage, 0, self::MAX_ERROR_MESSAGE_LENGTH);
    }

    public static function responded(int $httpStatus, ProbeTimings $timings, ?string $body = null): self
    {
        return new self($httpStatus, null, $timings, null, $body);
    }

    public static function failed(FailureKind $failureKind, string $errorMessage, ProbeTimings $timings, ?int $httpStatus = null): self
    {
        return new self($httpStatus, $failureKind, $timings, $errorMessage);
    }

    public function withoutBody(): self
    {
        return new self($this->httpStatus, $this->failureKind, $this->timings, $this->errorMessage);
    }

    public function responseMs(): ?int
    {
        return $this->httpStatus === null ? null : $this->timings->totalMs;
    }
}
