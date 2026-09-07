<?php

declare(strict_types=1);

namespace App\Alerting\Values;

use Carbon\CarbonImmutable;

final readonly class AlertField
{
    private function __construct(
        public string $label,
        public string $text,
        public ?CarbonImmutable $time,
    ) {}

    public static function text(string $label, string $text): self
    {
        return new self($label, $text, null);
    }

    public static function time(string $label, CarbonImmutable $time, string $utcText): self
    {
        return new self($label, $utcText, $time);
    }
}
