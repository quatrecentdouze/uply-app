<?php

declare(strict_types=1);

namespace App\Incidents\Values;

final readonly class MonitorNames
{
    public function __construct(private array $names) {}

    public function of(string $monitorId): ?string
    {
        $name = $this->names[$monitorId] ?? null;

        return is_string($name) ? $name : null;
    }
}
