<?php

declare(strict_types=1);

namespace App\StatusPages\Values;

use App\StatusPages\Enums\PublicMonitorState;

final readonly class PublicMonitor
{
    public function __construct(
        public string $id,
        public string $displayName,
        public PublicMonitorState $state,
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

        return ['id' => $this->id, 'display_name' => $this->displayName, 'state' => $this->state->value, 'uptime_90d' => $this->uptime90d, 'bars' => $bars];
    }

    public static function fromSnapshot(Snapshot $snapshot): self
    {
        $bars = [];
        foreach ($snapshot->list('bars') as $bar) {
            if ($bar instanceof Snapshot) {
                $bars[] = PublicBar::fromSnapshot($bar);
            }
        }

        return new self($snapshot->text('id'), $snapshot->text('display_name'), PublicMonitorState::tryFrom($snapshot->text('state')) ?? PublicMonitorState::Pending, $snapshot->optionalFloat('uptime_90d'), $bars);
    }
}
