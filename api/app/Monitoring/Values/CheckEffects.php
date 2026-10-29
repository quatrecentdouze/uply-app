<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

final readonly class CheckEffects
{
    public function __construct(
        public StateAdvance $advance,
        public bool $inMaintenance,
        public FlappingStep $flapping,
        public ?CertificateWatch $certificate,
    ) {}
}
