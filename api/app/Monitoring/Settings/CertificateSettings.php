<?php

declare(strict_types=1);

namespace App\Monitoring\Settings;

final readonly class CertificateSettings
{
    public function __construct(
        public array $expiryAlertDays,
    ) {}
}
