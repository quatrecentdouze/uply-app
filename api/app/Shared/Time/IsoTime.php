<?php

declare(strict_types=1);

namespace App\Shared\Time;

use Carbon\CarbonInterface;

final class IsoTime
{
    public static function format(?CarbonInterface $time): ?string
    {
        return $time?->toImmutable()->utc()->toIso8601ZuluString();
    }
}
