<?php

declare(strict_types=1);

namespace App\Alerting\Storage;

use App\Alerting\Enums\DecisionOutcome;
use App\Alerting\Enums\DeliveryKind;
use App\Alerting\Enums\DeliveryStatus;
use App\Alerting\Enums\DisabledReason;
use App\Alerting\Events\AlertDeliveredEvent;
use App\Alerting\Models\AlertChannel;
use App\Alerting\Models\AlertDelivery;
use App\Alerting\Values\DeliveryDecision;
use App\Alerting\Values\QueuedDelivery;
use App\Shared\Clock;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

final readonly class DeliveryStore
{
    public function __construct(
        private ConnectionInterface $database,
        private Dispatcher $events,
        private Clock $clock,
    ) {}

    public function createForIncident(string $incidentId, string $monitorId, DeliveryKind $kind): array
    {
        return $this->database->transaction(function () use ($incidentId, $monitorId, $kind): array {
            $channels = AlertChannel::query()
                ->where('is_enabled', true)
                ->whereIn('id', fn (Builder $links): Builder => $links->select('alert_channel_id')->from('alert_channel_monitor')->where('monitor_id', $monitorId))
                ->orderBy('id')
                ->get(['id', 'type']);

            $queued = [];
            foreach ($channels as $channel) {
                $deliveryId = $this->insert($channel->id, $incidentId, $kind);
                if ($deliveryId !== null) {
                    $queued[] = new QueuedDelivery($deliveryId, $channel->id, $channel->type->usesWebhook());
                }
            }

            return $queued;
        });
    }

    public function createTest(AlertChannel $channel): QueuedDelivery
    {
        $deliveryId = $this->insert($channel->id, null, DeliveryKind::Test);

        return new QueuedDelivery((string) $deliveryId, $channel->id, $channel->type->usesWebhook());
    }

    public function pending(string $deliveryId): ?AlertDelivery
    {
        return AlertDelivery::query()->whereKey($deliveryId)->where('status', DeliveryStatus::Pending)->first();
    }

    public function channel(string $channelId): ?AlertChannel
    {
        return AlertChannel::query()->whereKey($channelId)->first();
    }

    public function record(string $deliveryId, DeliveryDecision $decision, int $attempts): void
    {
        $this->database->transaction(function () use ($deliveryId, $decision, $attempts): void {
            $delivery = AlertDelivery::query()->whereKey($deliveryId)->lockForUpdate()->first();
            $channel = $delivery === null ? null : AlertChannel::query()->whereKey($delivery->alert_channel_id)->lockForUpdate()->first();
            if ($delivery === null || $channel === null || ! $delivery->isPending()) {
                return;
            }
            $now = $this->clock->now();
            $delivery->attempts = $attempts;
            $delivery->last_error = $decision->lastError;
            if ($decision->outcome === DecisionOutcome::Sent) {
                $delivery->status = DeliveryStatus::Sent;
                $delivery->sent_at = $now;
            }
            if ($decision->outcome === DecisionOutcome::Failed) {
                $delivery->status = DeliveryStatus::Failed;
            }
            if ($decision->disablesChannel) {
                $channel->disable(DisabledReason::WebhookRejected);
                $channel->save();
            }
            $delivery->save();

            if ($decision->outcome !== DecisionOutcome::Retry) {
                $this->events->dispatch(new AlertDeliveredEvent(
                    deliveryId: $delivery->id,
                    organizationId: $channel->organization_id,
                    channelId: $channel->id,
                    channelType: $channel->type,
                    incidentId: $delivery->incident_id,
                    kind: $delivery->kind,
                    status: $delivery->status,
                    attempts: $attempts,
                    finishedAt: $now,
                ));
            }
        });
    }

    private function insert(string $channelId, ?string $incidentId, DeliveryKind $kind): ?string
    {
        $now = $this->clock->now();
        $deliveryId = (new AlertDelivery)->newUniqueId();
        $inserted = AlertDelivery::query()->insertOrIgnore([
            'id' => $deliveryId,
            'alert_channel_id' => $channelId,
            'incident_id' => $incidentId,
            'kind' => $kind->value,
            'status' => DeliveryStatus::Pending->value,
            'attempts' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $inserted === 1 ? $deliveryId : null;
    }
}
