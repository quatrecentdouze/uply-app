<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class CertificateWatch
{
    public function __construct(
        public CarbonImmutable $expiresAt,
        public ?int $alertedDays,
        public ?int $alertDays,
    ) {
        if ($alertDays !== null && $alertDays !== $alertedDays) {
            throw new InvalidArgumentException('an alert due now is the smallest threshold alerted');
        }
    }
}
