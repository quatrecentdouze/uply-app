<?php

declare(strict_types=1);

namespace App\Alerting\Values;

use InvalidArgumentException;

final readonly class WebhookUrl
{
    private const string SCHEME = 'https';

    private function __construct(
        public string $value,
    ) {}

    public static function parse(string $url, WebhookPolicy $policy): self
    {
        $parts = parse_url(trim($url));
        if ($parts === false || ($parts['scheme'] ?? '') !== self::SCHEME) {
            throw new InvalidArgumentException('a webhook url must use https');
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException('a webhook url carries no credentials, port or fragment');
        }
        if (! $policy->allowsHost($parts['host'] ?? '')) {
            throw new InvalidArgumentException('the webhook host is not allowed for this channel type');
        }
        if (! $policy->allowsPath($parts['path'] ?? '')) {
            throw new InvalidArgumentException('the webhook path is not the path of this channel type');
        }

        return new self(trim($url));
    }
}
