<?php

declare(strict_types=1);

namespace App\StatusPages\Values;

final readonly class PublicBar
{
    public function __construct(
        public string $date,
        public ?float $uptimePercent,
        public bool $maintenance,
    ) {}

    public function toSnapshot(): array
    {
        return ['date' => $this->date, 'uptime_percent' => $this->uptimePercent, 'maintenance' => $this->maintenance];
    }

    public static function fromSnapshot(Snapshot $snapshot): self
    {
        return new self($snapshot->text('date'), $snapshot->optionalFloat('uptime_percent'), $snapshot->flag('maintenance'));
    }
}
