<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

use App\Monitoring\Enums\HttpMethod;
use App\Monitoring\Enums\KeywordMatch;

final readonly class CheckDefinition
{
    public function __construct(
        public string $url,
        public HttpMethod $method,
        public AcceptedStatusCodes $acceptedStatusCodes,
        public bool $followRedirects,
        public int $intervalSeconds,
        public bool $isPaused,
        public MonitorRequest $request,
        public ?string $keyword,
        public KeywordMatch $keywordMatch,
    ) {}

    public function judgesLike(self $other): bool
    {
        return $this->url === $other->url
            && $this->method === $other->method
            && $this->acceptedStatusCodes->toTexts() === $other->acceptedStatusCodes->toTexts()
            && $this->followRedirects === $other->followRedirects
            && $this->request->sameAs($other->request)
            && $this->keyword === $other->keyword
            && $this->keywordMatch === $other->keywordMatch;
    }
}
