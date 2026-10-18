<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

use App\Monitoring\Enums\HttpMethod;

final readonly class ProbeHop
{
    public function __construct(
        public HttpMethod $method,
        public bool $sendsBody,
        public bool $sendsUserHeaders,
    ) {}

    public static function first(HttpMethod $method, MonitorRequest $request): self
    {
        return new self($method, $method->sendsBody() && $request->hasBody(), true);
    }
}
