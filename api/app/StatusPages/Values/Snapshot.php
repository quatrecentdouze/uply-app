<?php

declare(strict_types=1);

namespace App\StatusPages\Values;

use InvalidArgumentException;

final readonly class Snapshot
{
    public function __construct(private array $values) {}

    public function text(string $key): string
    {
        $value = $this->values[$key] ?? null;

        return is_string($value) ? $value : throw new InvalidArgumentException("snapshot key {$key} is not text");
    }

    public function optionalText(string $key): ?string
    {
        $value = $this->values[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    public function optionalFloat(string $key): ?float
    {
        $value = $this->values[$key] ?? null;

        return is_float($value) || is_int($value) ? (float) $value : null;
    }

    public function optionalInteger(string $key): ?int
    {
        $value = $this->values[$key] ?? null;

        return is_int($value) ? $value : null;
    }

    public function flag(string $key): bool
    {
        return ($this->values[$key] ?? null) === true;
    }

    public function list(string $key): array
    {
        $value = $this->values[$key] ?? null;
        $items = [];
        foreach (is_array($value) ? $value : [] as $item) {
            if (is_array($item)) {
                $items[] = new self($item);
            }
        }

        return $items;
    }
}
