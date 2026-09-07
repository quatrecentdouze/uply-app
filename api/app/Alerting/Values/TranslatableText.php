<?php

declare(strict_types=1);

namespace App\Alerting\Values;

final readonly class TranslatableText
{
    public function __construct(
        public string $key,
        public array $parameters = [],
        public ?string $fallbackKey = null,
    ) {}
}
