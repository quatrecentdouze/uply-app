<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

use App\Monitoring\Enums\HttpMethod;

final readonly class CheckDefinition
{
    public function __construct(
        public string $url,
        public HttpMethod $method,
        public AcceptedStatusCodes $acceptedStatusCodes,
        public bool $followRedirects,
        public int $intervalSeconds,
        public bool $isPaused,
    ) {}

    public function judgesLike(self $other): bool
    {
        return $this->url === $other->url
            && $this->method === $other->method
            && $this->acceptedStatusCodes->toTexts() === $other->acceptedStatusCodes->toTexts()
            && $this->followRedirects === $other->followRedirects;
    }
}
