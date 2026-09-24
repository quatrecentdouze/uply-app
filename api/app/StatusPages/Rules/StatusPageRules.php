<?php

declare(strict_types=1);

namespace App\StatusPages\Rules;

final class StatusPageRules
{
    public static function displayName(?string $given, string $monitorName, int $maxLength): string
    {
        $name = $given !== null && trim($given) !== '' ? trim($given) : trim($monitorName);

        return mb_substr($name, 0, $maxLength);
    }
}
