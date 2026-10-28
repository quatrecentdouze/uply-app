<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

use Carbon\CarbonImmutable;

final readonly class FlappingState
{
    public array $stateChanges;

    public function __construct(
        public ?CarbonImmutable $flappingSince,
        array $stateChanges,
    ) {
        $changes = array_values(array_filter($stateChanges, fn (mixed $change): bool => $change instanceof CarbonImmutable));
        usort($changes, fn (CarbonImmutable $first, CarbonImmutable $second): int => $first <=> $second);
        $this->stateChanges = $changes;
    }

    public static function none(): self
    {
        return new self(null, []);
    }

    public static function fromStored(?CarbonImmutable $flappingSince, array $storedChanges): self
    {
        $changes = [];
        foreach ($storedChanges as $stored) {
            if (is_string($stored)) {
                $changes[] = CarbonImmutable::parse($stored)->utc();
            }
        }

        return new self($flappingSince, $changes);
    }

    public function isFlapping(): bool
    {
        return $this->flappingSince !== null;
    }

    public function firstChangeAt(): ?CarbonImmutable
    {
        return $this->changeAt(array_key_first($this->stateChanges));
    }

    public function lastChangeAt(): ?CarbonImmutable
    {
        return $this->changeAt(array_key_last($this->stateChanges));
    }

    public function changeCount(): int
    {
        return count($this->stateChanges);
    }

    public function toStored(): array
    {
        return array_map(fn (CarbonImmutable $change): string => $change->utc()->toIso8601ZuluString(), $this->stateChanges);
    }

    private function changeAt(?int $index): ?CarbonImmutable
    {
        $change = $index === null ? null : $this->stateChanges[$index];

        return $change instanceof CarbonImmutable ? $change : null;
    }
}
