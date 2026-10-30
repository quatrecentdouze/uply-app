<?php

declare(strict_types=1);

namespace App\Alerting\Actions;

use App\Alerting\Jobs\AlertQueue;
use App\Alerting\Messages\AlertMessageBuilder;
use App\Alerting\Models\AlertChannel;
use App\Alerting\Rules\ReminderRules;
use App\Alerting\Storage\ReminderQuery;
use App\Alerting\Values\AlertMessage;
use App\Alerting\Values\ReminderHistory;
use App\Incidents\Contracts\OpenIncident;
use App\Monitoring\Contracts\MonitorStatus;
use App\Monitoring\Contracts\MonitorView;
use Carbon\CarbonImmutable;

final readonly class SendReminders
{
    public function __construct(
        private ReminderContext $context,
        private ReminderQuery $reminders,
        private AlertQueue $alerts,
        private AlertMessageBuilder $messages,
    ) {}

    public function __invoke(): int
    {
        $now = $this->context->clock->now();
        $queued = 0;
        foreach ($this->context->incidents->all() as $incident) {
            if ($incident instanceof OpenIncident) {
                $queued += $this->remind($incident, $now);
            }
        }

        return $queued;
    }

    private function remind(OpenIncident $incident, CarbonImmutable $now): int
    {
        $channels = [];
        foreach ($this->reminders->channelsOf($incident->monitorId) as $channel) {
            if ($channel instanceof AlertChannel && $channel->reminder_interval_minutes !== null) {
                $channels[$channel->id] = $channel;
            }
        }
        $monitor = $channels === [] ? null : $this->context->monitors->find($incident->monitorId);
        if ($monitor === null) {
            return 0;
        }
        $silenced = $this->silenced($monitor, $now);
        $histories = $this->reminders->histories($incident->id, array_keys($channels));
        $message = null;
        $queued = 0;
        foreach ($channels as $channel) {
            $interval = $channel->reminder_interval_minutes;
            if ($interval === null) {
                continue;
            }
            $history = $histories[$channel->id] ?? null;
            $number = ReminderRules::nextNumber(
                $history instanceof ReminderHistory ? $history : ReminderHistory::none(),
                $incident->startedAt,
                $now,
                $interval,
                $this->context->settings->reminders->maxPerIncident,
                $silenced,
            );
            if ($number === null) {
                continue;
            }
            $message ??= $this->message($monitor, $incident, $now);
            $queued += $this->alerts->queueReminder($channel, $incident->id, $number, $message) ? 1 : 0;
        }

        return $queued;
    }

    private function silenced(MonitorView $monitor, CarbonImmutable $now): bool
    {
        return $monitor->status === MonitorStatus::Paused
            || $monitor->flappingSince !== null
            || $this->context->maintenance->covers($monitor->id, $now);
    }

    private function message(MonitorView $monitor, OpenIncident $incident, CarbonImmutable $now): AlertMessage
    {
        return $this->messages->reminder($monitor, $incident->id, $incident->startedAt, $incident->causeFailureKind, $incident->causeHttpStatus, $now);
    }
}
