<?php

declare(strict_types=1);

namespace App\Realtime\Broadcasts;

use App\Shared\QueueName;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class MonitorCheckedBroadcast implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    public function __construct(
        public string $organizationId,
        public string $monitorId,
        public string $checkedAt,
        public string $outcome,
        public ?int $responseMs,
        public string $state,
        public bool $inMaintenance,
    ) {}

    public function broadcastOn(): array
    {
        return [OrganizationChannel::of($this->organizationId)];
    }

    public function broadcastAs(): string
    {
        return 'monitor.checked';
    }

    public function broadcastWith(): array
    {
        return [
            'monitor_id' => $this->monitorId,
            'checked_at' => $this->checkedAt,
            'outcome' => $this->outcome,
            'response_ms' => $this->responseMs,
            'state' => $this->state,
            'in_maintenance' => $this->inMaintenance,
        ];
    }

    public function broadcastQueue(): string
    {
        return QueueName::Realtime->value;
    }
}
