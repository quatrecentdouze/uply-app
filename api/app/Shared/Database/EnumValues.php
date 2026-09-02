<?php

declare(strict_types=1);

namespace App\Shared\Database;

use BackedEnum;

final class EnumValues
{
    public static function sqlList(BackedEnum ...$cases): string
    {
        return implode(', ', array_map(static fn (BackedEnum $case): string => "'".str_replace("'", "''", (string) $case->value)."'", $cases));
    }
}
