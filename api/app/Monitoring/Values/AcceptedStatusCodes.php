<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

use InvalidArgumentException;

final readonly class AcceptedStatusCodes
{
    private array $ranges;

    public function __construct(StatusCodeRange ...$ranges)
    {
        if ($ranges === []) {
            throw new InvalidArgumentException('accepted status codes need at least one range');
        }
        $this->ranges = array_values($ranges);
    }

    public static function parse(array $texts): self
    {
        $ranges = [];
        foreach ($texts as $text) {
            if (! is_string($text)) {
                throw new InvalidArgumentException('accepted status codes must be texts like 200-299');
            }
            $ranges[] = StatusCodeRange::parse($text);
        }

        return new self(...$ranges);
    }

    public function accepts(int $code): bool
    {
        foreach ($this->ranges as $range) {
            if ($range->contains($code)) {
                return true;
            }
        }

        return false;
    }

    public function ranges(): array
    {
        return $this->ranges;
    }

    public function toTexts(): array
    {
        return array_map(static fn (StatusCodeRange $range): string => $range->toText(), $this->ranges);
    }
}
