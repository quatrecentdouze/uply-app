<?php

declare(strict_types=1);

namespace App\Alerting\Jobs;

use App\Alerting\Enums\DeliveryKind;
use App\Alerting\Models\AlertChannel;
use App\Alerting\Settings\AlertingSettings;
use App\Alerting\Storage\DeliveryStore;
use App\Alerting\Values\AlertMessage;
use App\Alerting\Values\QueuedDelivery;
use Illuminate\Contracts\Bus\Dispatcher;

final readonly class AlertQueue
{
    public function __construct(
        private DeliveryStore $deliveries,
        private Dispatcher $bus,
        private AlertingSettings $settings,
    ) {}

    public function queueForIncident(string $incidentId, string $monitorId, DeliveryKind $kind, AlertMessage $message): int
    {
        $queued = $this->deliveries->createForIncident($incidentId, $monitorId, $kind);
        foreach ($queued as $delivery) {
            if ($delivery instanceof QueuedDelivery) {
                $this->dispatch($delivery, $message);
            }
        }

        return count($queued);
    }

    public function queueTest(AlertChannel $channel, AlertMessage $message): string
    {
        $delivery = $this->deliveries->createTest($channel);
        $this->dispatch($delivery, $message);

        return $delivery->deliveryId;
    }

    private function dispatch(QueuedDelivery $delivery, AlertMessage $message): void
    {
        $this->bus->dispatch(new SendAlertJob(
            $delivery->deliveryId,
            $delivery->channelId,
            $delivery->usesWebhook,
            $message,
            $this->settings->delivery->retryPolicy(),
        ));
    }
}
