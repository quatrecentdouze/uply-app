<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

use App\Monitoring\Enums\HttpMethod;
use InvalidArgumentException;

final readonly class ProbeRequest
{
    public function __construct(
        public string $url,
        public HttpMethod $method,
        public int $timeoutSeconds,
        public bool $followRedirects,
        public MonitorRequest $request,
        public bool $readsBody = false,
    ) {
        if ($timeoutSeconds < 1) {
            throw new InvalidArgumentException('a probe needs a timeout of at least one second');
        }
    }
}
