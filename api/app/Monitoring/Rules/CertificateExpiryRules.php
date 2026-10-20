<?php

declare(strict_types=1);

namespace App\Monitoring\Rules;

use App\Monitoring\Values\CertificateWatch;
use Carbon\CarbonImmutable;

final class CertificateExpiryRules
{
    private const int SECONDS_PER_DAY = 86_400;

    public static function watch(
        ?CarbonImmutable $knownExpiry,
        ?int $knownAlertedDays,
        CarbonImmutable $seenExpiry,
        CarbonImmutable $now,
        array $thresholdDays,
    ): CertificateWatch {
        $renewed = $knownExpiry === null || $knownExpiry->getTimestamp() !== $seenExpiry->getTimestamp();
        $alertedDays = $renewed ? null : $knownAlertedDays;
        $due = self::reachedThreshold($seenExpiry, $now, $thresholdDays);
        if ($due === null || ($alertedDays !== null && $due >= $alertedDays)) {
            return new CertificateWatch($seenExpiry, $alertedDays, null);
        }

        return new CertificateWatch($seenExpiry, $due, $due);
    }

    private static function reachedThreshold(CarbonImmutable $expiresAt, CarbonImmutable $now, array $thresholdDays): ?int
    {
        $remainingSeconds = $expiresAt->getTimestamp() - $now->getTimestamp();
        if ($remainingSeconds <= 0) {
            return null;
        }
        $reached = null;
        foreach ($thresholdDays as $days) {
            if (is_int($days) && $remainingSeconds <= $days * self::SECONDS_PER_DAY && ($reached === null || $days < $reached)) {
                $reached = $days;
            }
        }

        return $reached;
    }
}
