<?php

declare(strict_types=1);

namespace App\Realtime\Broadcasts;

use App\Shared\QueueName;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class MonitorFlappingBroadcast implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    public function __construct(
        public string $organizationId,
        public string $monitorId,
        public ?string $flappingSince,
    ) {}

    public function broadcastOn(): array
    {
        return [OrganizationChannel::of($this->organizationId)];
    }

    public function broadcastAs(): string
    {
        return 'monitor.flapping';
    }

    public function broadcastWith(): array
    {
        return [
            'monitor_id' => $this->monitorId,
            'flapping_since' => $this->flappingSince,
        ];
    }

    public function broadcastQueue(): string
    {
        return QueueName::Realtime->value;
    }
}
