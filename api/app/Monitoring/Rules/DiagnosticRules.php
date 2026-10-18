<?php

declare(strict_types=1);

namespace App\Monitoring\Rules;

final class DiagnosticRules
{
    public const string REDACTED = '[secret]';

    public static function withoutSecrets(string $message, array $secrets): string
    {
        $texts = [];
        foreach ($secrets as $secret) {
            if (is_string($secret) && $secret !== '') {
                $texts[] = $secret;
            }
        }
        usort($texts, fn (string $left, string $right): int => strlen($right) <=> strlen($left));

        return str_replace($texts, self::REDACTED, $message);
    }
}
