<?php

declare(strict_types=1);

namespace App\Realtime\Broadcasts;

use App\Shared\QueueName;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class IncidentResolvedBroadcast implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    public function __construct(
        public string $organizationId,
        public string $incidentId,
        public string $monitorId,
        public string $resolvedAt,
        public int $durationSeconds,
    ) {}

    public function broadcastOn(): array
    {
        return [OrganizationChannel::of($this->organizationId)];
    }

    public function broadcastAs(): string
    {
        return 'incident.resolved';
    }

    public function broadcastWith(): array
    {
        return [
            'incident_id' => $this->incidentId,
            'monitor_id' => $this->monitorId,
            'resolved_at' => $this->resolvedAt,
            'duration_seconds' => $this->durationSeconds,
        ];
    }

    public function broadcastQueue(): string
    {
        return QueueName::Realtime->value;
    }
}
