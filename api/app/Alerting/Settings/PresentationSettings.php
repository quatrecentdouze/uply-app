<?php

declare(strict_types=1);

namespace App\Alerting\Settings;

use App\Alerting\Enums\DeliveryKind;

final readonly class PresentationSettings
{
    public function __construct(
        public string $frontendUrl,
        public string $downColor,
        public string $upColor,
        public string $testColor,
        public string $certificateExpiringColor,
        public string $discordUsername,
        public int $discordTitleMaxLength,
        public int $discordDescriptionMaxLength,
        public int $discordFieldValueMaxLength,
    ) {}

    public function colorFor(DeliveryKind $kind): string
    {
        return match ($kind) {
            DeliveryKind::Down => $this->downColor,
            DeliveryKind::Up => $this->upColor,
            DeliveryKind::Test => $this->testColor,
            DeliveryKind::CertificateExpiring => $this->certificateExpiringColor,
        };
    }

    public function monitorLink(string $monitorId): string
    {
        return rtrim($this->frontendUrl, '/')."/monitors/{$monitorId}";
    }

    public function incidentLink(string $incidentId): string
    {
        return rtrim($this->frontendUrl, '/')."/incidents/{$incidentId}";
    }
}
