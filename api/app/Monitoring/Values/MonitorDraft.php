<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

use App\Monitoring\Enums\HttpMethod;
use InvalidArgumentException;

final readonly class MonitorDraft
{
    public function __construct(
        public string $name,
        public string $url,
        public HttpMethod $method,
        public int $intervalSeconds,
        public int $timeoutSeconds,
        public AcceptedStatusCodes $acceptedStatusCodes,
        public bool $followRedirects,
    ) {
        if (trim($name) === '' || trim($url) === '') {
            throw new InvalidArgumentException('a monitor needs a name and a url');
        }
        if ($intervalSeconds < 1 || $timeoutSeconds < 1) {
            throw new InvalidArgumentException('a monitor needs a positive interval and timeout');
        }
    }
}
