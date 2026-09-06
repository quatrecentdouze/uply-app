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
        };
    }

    public function incidentLink(string $incidentId): string
    {
        return rtrim($this->frontendUrl, '/')."/incidents/{$incidentId}";
    }
}
