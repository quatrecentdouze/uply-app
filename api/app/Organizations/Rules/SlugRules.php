<?php

declare(strict_types=1);

namespace App\Organizations\Rules;

final class SlugRules
{
    private const string FILLER = 'org';

    public static function fit(string $slug, int $minLength, int $maxLength): string
    {
        $normalized = self::trimmed((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($slug)), $maxLength);
        if (strlen($normalized) >= $minLength) {
            return $normalized;
        }

        return self::trimmed(ltrim($normalized.'-'.self::FILLER, '-'), $maxLength);
    }

    public static function firstFree(string $base, array $taken, int $maxLength): string
    {
        for ($attempt = 1; ; $attempt++) {
            $candidate = self::candidate($base, $attempt, $maxLength);
            if (! in_array($candidate, $taken, true)) {
                return $candidate;
            }
        }
    }

    private static function candidate(string $base, int $attempt, int $maxLength): string
    {
        if ($attempt === 1) {
            return $base;
        }
        $suffix = '-'.$attempt;

        return rtrim(substr($base, 0, $maxLength - strlen($suffix)), '-').$suffix;
    }

    private static function trimmed(string $slug, int $maxLength): string
    {
        return trim(substr(trim($slug, '-'), 0, $maxLength), '-');
    }
}
