<?php

declare(strict_types=1);

namespace App\StatusPages\Values;

final readonly class PublicMonitor
{
    public function __construct(
        public string $id,
        public string $displayName,
        public string $state,
        public ?float $uptime90d,
        public array $bars,
    ) {}

    public function toSnapshot(): array
    {
        $bars = [];
        foreach ($this->bars as $bar) {
            if ($bar instanceof PublicBar) {
                $bars[] = $bar->toSnapshot();
            }
        }

        return ['id' => $this->id, 'display_name' => $this->displayName, 'state' => $this->state, 'uptime_90d' => $this->uptime90d, 'bars' => $bars];
    }

    public static function fromSnapshot(Snapshot $snapshot): self
    {
        $bars = [];
        foreach ($snapshot->list('bars') as $bar) {
            if ($bar instanceof Snapshot) {
                $bars[] = PublicBar::fromSnapshot($bar);
            }
        }

        return new self($snapshot->text('id'), $snapshot->text('display_name'), $snapshot->text('state'), $snapshot->optionalFloat('uptime_90d'), $bars);
    }
}
