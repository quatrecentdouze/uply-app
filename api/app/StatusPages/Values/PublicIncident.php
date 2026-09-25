<?php

declare(strict_types=1);

namespace App\StatusPages\Values;

final readonly class PublicIncident
{
    public function __construct(
        public string $displayName,
        public string $startedAt,
        public ?string $resolvedAt,
        public ?int $durationSeconds,
    ) {}

    public function toSnapshot(): array
    {
        return [
            'display_name' => $this->displayName,
            'started_at' => $this->startedAt,
            'resolved_at' => $this->resolvedAt,
            'duration_seconds' => $this->durationSeconds,
        ];
    }

    public static function fromSnapshot(Snapshot $snapshot): self
    {
        return new self(
            $snapshot->text('display_name'),
            $snapshot->text('started_at'),
            $snapshot->optionalText('resolved_at'),
            $snapshot->optionalInteger('duration_seconds'),
        );
    }
}
