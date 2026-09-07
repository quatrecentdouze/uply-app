<?php

declare(strict_types=1);

namespace App\Alerting\Values;

use App\Alerting\Enums\DeliveryKind;
use Carbon\CarbonImmutable;
use Closure;

final readonly class AlertMessage
{
    public array $fields;

    public function __construct(
        public DeliveryKind $kind,
        public string $title,
        public string $summary,
        public ?string $link,
        public string $linkLabel,
        public string $color,
        public CarbonImmutable $occurredAt,
        AlertField ...$fields,
    ) {
        $this->fields = array_values($fields);
    }

    public function mapFields(Closure $render): array
    {
        return array_map($render, $this->fields);
    }

    public function hasFields(): bool
    {
        return $this->fields !== [];
    }
}
