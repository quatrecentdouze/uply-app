<?php

declare(strict_types=1);

namespace App\Alerting\Rules;

use App\Alerting\Values\WebhookHint;

final class WebhookHintRules
{
    private const int VISIBLE_CHARACTERS = 4;

    private const int MIN_HIDDEN_CHARACTERS = 16;

    public static function hint(string $url): ?WebhookHint
    {
        $host = parse_url(trim($url), PHP_URL_HOST);
        $path = parse_url(trim($url), PHP_URL_PATH);
        if (! is_string($host) || $host === '' || ! is_string($path) || mb_strlen($path) < self::MIN_HIDDEN_CHARACTERS) {
            return null;
        }

        return new WebhookHint(strtolower($host), mb_substr($path, -self::VISIBLE_CHARACTERS));
    }
}
