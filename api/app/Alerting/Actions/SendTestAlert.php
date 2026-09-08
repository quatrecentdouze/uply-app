<?php

declare(strict_types=1);

namespace App\Alerting\Actions;

use App\Alerting\Jobs\AlertQueue;
use App\Alerting\Messages\AlertMessageBuilder;
use App\Alerting\Storage\DeliveryStore;
use App\Alerting\Values\TestAlertOutcome;
use App\Shared\Clock;

final readonly class SendTestAlert
{
    public function __construct(
        private DeliveryStore $deliveries,
        private TestSendLimiter $limiter,
        private AlertQueue $alerts,
        private AlertMessageBuilder $messages,
    ) {}

    public function __invoke(string $channelId, Clock $clock): TestAlertOutcome
    {
        $channel = $this->deliveries->channel($channelId);
        if ($channel === null) {
            return TestAlertOutcome::channelNotFound();
        }
        $retryIn = $this->limiter->secondsUntilAllowed($channel->id);
        if ($retryIn !== null) {
            return TestAlertOutcome::limited($retryIn);
        }

        return TestAlertOutcome::queued($this->alerts->queueTest($channel, $this->messages->test($channel->name, $clock->now())));
    }
}
