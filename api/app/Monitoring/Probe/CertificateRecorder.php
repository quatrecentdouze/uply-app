<?php

declare(strict_types=1);

namespace App\Monitoring\Probe;

use App\Monitoring\Rules\CertificateInfoRules;
use App\Monitoring\Values\TargetUrl;
use Carbon\CarbonImmutable;

final class CertificateRecorder
{
    private const string HTTPS = 'https';

    private const string CERTIFICATES = 'certinfo';

    public ?CarbonImmutable $expiresAt = null;

    private bool $read = false;

    public function wants(TargetUrl $target): bool
    {
        return ! $this->read && $target->scheme === self::HTTPS;
    }

    public function record(TargetUrl $target, array $handlerStats): void
    {
        if (! $this->wants($target)) {
            return;
        }
        $this->read = true;
        $this->expiresAt = CertificateInfoRules::leafExpiry($handlerStats[self::CERTIFICATES] ?? null);
    }
}
