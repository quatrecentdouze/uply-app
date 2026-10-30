<?php

declare(strict_types=1);

namespace App\Alerting\Jobs;

use App\Alerting\Enums\DeliveryKind;
use App\Alerting\Models\AlertChannel;
use App\Alerting\Settings\AlertingSettings;
use App\Alerting\Storage\DeliveryStore;
use App\Alerting\Values\AlertMessage;
use App\Alerting\Values\QueuedDelivery;
use Carbon\CarbonImmutable;
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
        return $this->dispatchAll($this->deliveries->createForIncident($incidentId, $monitorId, $kind), $message);
    }

    public function queueForCertificate(string $monitorId, CarbonImmutable $expiresAt, int $days, AlertMessage $message): int
    {
        return $this->dispatchAll($this->deliveries->createForCertificate($monitorId, $expiresAt, $days), $message);
    }

    public function queueForFlapping(string $monitorId, DeliveryKind $kind, CarbonImmutable $flappingSince, AlertMessage $message): int
    {
        return $this->dispatchAll($this->deliveries->createForFlapping($monitorId, $kind, $flappingSince), $message);
    }

    public function queueReminder(AlertChannel $channel, string $incidentId, int $number, AlertMessage $message): bool
    {
        $delivery = $this->deliveries->createReminder($channel, $incidentId, $number);
        if ($delivery === null) {
            return false;
        }
        $this->dispatch($delivery, $message);

        return true;
    }

    public function queueTest(AlertChannel $channel, AlertMessage $message): string
    {
        $delivery = $this->deliveries->createTest($channel);
        $this->dispatch($delivery, $message);

        return $delivery->deliveryId;
    }

    private function dispatchAll(array $queued, AlertMessage $message): int
    {
        foreach ($queued as $delivery) {
            if ($delivery instanceof QueuedDelivery) {
                $this->dispatch($delivery, $message);
            }
        }

        return count($queued);
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
