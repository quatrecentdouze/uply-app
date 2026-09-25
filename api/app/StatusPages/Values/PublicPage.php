<?php

declare(strict_types=1);

namespace App\StatusPages\Values;

final readonly class PublicPage
{
    public function __construct(
        public string $title,
        public ?string $description,
        public string $overallState,
        public array $monitors,
        public array $incidents,
    ) {}

    public function toSnapshot(): array
    {
        $monitors = [];
        foreach ($this->monitors as $monitor) {
            if ($monitor instanceof PublicMonitor) {
                $monitors[] = $monitor->toSnapshot();
            }
        }
        $incidents = [];
        foreach ($this->incidents as $incident) {
            if ($incident instanceof PublicIncident) {
                $incidents[] = $incident->toSnapshot();
            }
        }

        return [
            'title' => $this->title,
            'description' => $this->description,
            'overall_state' => $this->overallState,
            'monitors' => $monitors,
            'incidents' => $incidents,
        ];
    }

    public static function fromSnapshot(array $values): self
    {
        $snapshot = new Snapshot($values);
        $monitors = [];
        foreach ($snapshot->list('monitors') as $monitor) {
            if ($monitor instanceof Snapshot) {
                $monitors[] = PublicMonitor::fromSnapshot($monitor);
            }
        }
        $incidents = [];
        foreach ($snapshot->list('incidents') as $incident) {
            if ($incident instanceof Snapshot) {
                $incidents[] = PublicIncident::fromSnapshot($incident);
            }
        }

        return new self($snapshot->text('title'), $snapshot->optionalText('description'), $snapshot->text('overall_state'), $monitors, $incidents);
    }
}
