<?php

declare(strict_types=1);

namespace App\Realtime\Broadcasts;

use App\Shared\QueueName;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class MonitorStateChangedBroadcast implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    public function __construct(
        public string $organizationId,
        public string $monitorId,
        public string $state,
        public string $changedAt,
    ) {}

    public function broadcastOn(): array
    {
        return [OrganizationChannel::of($this->organizationId)];
    }

    public function broadcastAs(): string
    {
        return 'monitor.state-changed';
    }

    public function broadcastWith(): array
    {
        return [
            'monitor_id' => $this->monitorId,
            'state' => $this->state,
            'changed_at' => $this->changedAt,
        ];
    }

    public function broadcastQueue(): string
    {
        return QueueName::Realtime->value;
    }
}
