<?php

declare(strict_types=1);

namespace App\Alerting\Jobs;

use App\Alerting\Enums\DecisionOutcome;
use App\Alerting\Enums\DeliveryKind;
use App\Alerting\Rules\DeliveryRules;
use App\Alerting\Senders\ChannelSender;
use App\Alerting\Settings\AlertingSettings;
use App\Alerting\Storage\DeliveryStore;
use App\Alerting\Values\AlertMessage;
use App\Alerting\Values\DeliveryDecision;
use App\Alerting\Values\RetryPolicy;
use App\Shared\QueueName;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;

final class SendAlertJob implements ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;

    public const string WEBHOOK_LIMITER = 'alert-webhooks';

    public int $tries = 0;

    public int $maxExceptions;

    public array $backoff;

    public function __construct(
        public readonly string $deliveryId,
        public readonly string $channelId,
        public readonly bool $usesWebhook,
        public readonly AlertMessage $message,
        RetryPolicy $policy,
    ) {
        $this->onQueue(QueueName::Alerts->value);
        $this->maxExceptions = $policy->maxAttempts;
        $this->backoff = $policy->backoffSeconds;
    }

    public function middleware(): array
    {
        return $this->usesWebhook ? [new RateLimited(self::WEBHOOK_LIMITER)] : [];
    }

    public function handle(DeliveryStore $deliveries, ChannelSender $sender, AlertingSettings $settings): void
    {
        $delivery = $deliveries->pending($this->deliveryId);
        $channel = $delivery === null ? null : $deliveries->channel($delivery->alert_channel_id);
        if ($delivery === null || $channel === null) {
            return;
        }
        if (! $channel->is_enabled && $delivery->kind !== DeliveryKind::Test) {
            $deliveries->record($delivery->id, DeliveryDecision::failed('channel disabled'), $delivery->attempts);

            return;
        }

        $attempts = $delivery->attempts + 1;
        $decision = DeliveryRules::decide($sender->send($channel, $this->message), $attempts, $settings->delivery->retryPolicy());
        $deliveries->record($delivery->id, $decision, $attempts);

        if ($decision->outcome === DecisionOutcome::Retry) {
            $this->release($decision->retryInSeconds ?? 0);
        }
    }
}
