<?php

declare(strict_types=1);

namespace App\Alerting\Rules;

use App\Alerting\Values\TranslatableText;

final class AlertTextRules
{
    private const string CAUSE_KEY_PREFIX = 'alerting.cause.';

    public const string UNKNOWN_CAUSE = 'unknown';

    private const string STABLE_ID_PATTERN = '/^[a-z][a-z_]*$/';

    private const string DURATION_KEY_PREFIX = 'alerting.duration.';

    private const array SECONDS_PER_UNIT = ['days' => 86400, 'hours' => 3600, 'minutes' => 60, 'seconds' => 1];

    public static function cause(string $failureKind, ?int $httpStatus): TranslatableText
    {
        $kind = preg_match(self::STABLE_ID_PATTERN, $failureKind) === 1 ? $failureKind : self::UNKNOWN_CAUSE;

        return new TranslatableText(
            self::CAUSE_KEY_PREFIX.$kind,
            ['status' => $httpStatus === null ? '' : (string) $httpStatus],
            self::CAUSE_KEY_PREFIX.self::UNKNOWN_CAUSE,
        );
    }

    public static function durationParts(int $seconds): array
    {
        $remaining = max(0, $seconds);
        $parts = [];
        foreach (self::SECONDS_PER_UNIT as $unit => $unitSeconds) {
            $count = intdiv($remaining, $unitSeconds);
            $remaining -= $count * $unitSeconds;
            if ($count > 0) {
                $parts[] = new TranslatableText(self::DURATION_KEY_PREFIX.$unit, ['count' => $count]);
            }
        }

        return $parts === [] ? [new TranslatableText(self::DURATION_KEY_PREFIX.'seconds', ['count' => 0])] : $parts;
    }
}
