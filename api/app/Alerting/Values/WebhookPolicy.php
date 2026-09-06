<?php

declare(strict_types=1);

namespace App\Alerting\Values;

use InvalidArgumentException;

final readonly class WebhookPolicy
{
    public function __construct(
        public array $hosts,
        public string $pathPattern,
    ) {
        if ($hosts === []) {
            throw new InvalidArgumentException('a webhook policy needs at least one host');
        }
        if (! self::isValidPattern($pathPattern)) {
            throw new InvalidArgumentException('the webhook path pattern is not a valid regular expression');
        }
    }

    private static function isValidPattern(string $pattern): bool
    {
        set_error_handler(static fn (): bool => true);
        try {
            return preg_match($pattern, '') !== false;
        } finally {
            restore_error_handler();
        }
    }

    public function allowsHost(string $host): bool
    {
        return in_array(strtolower($host), $this->hosts, true);
    }

    public function allowsPath(string $path): bool
    {
        return preg_match($this->pathPattern, $path) === 1;
    }
}
