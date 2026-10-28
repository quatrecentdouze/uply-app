<?php

declare(strict_types=1);

namespace App\Alerting\Messages;

use App\Alerting\Enums\DeliveryKind;
use App\Alerting\Rules\AlertTextRules;
use App\Alerting\Settings\AlertingSettings;
use App\Alerting\Values\AlertField;
use App\Alerting\Values\AlertMessage;
use App\Alerting\Values\TranslatableText;
use App\Monitoring\Contracts\MonitorView;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Translation\Translator;

final readonly class AlertMessageBuilder
{
    private const string UTC_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private Translator $translator,
        private AlertingSettings $settings,
    ) {}

    public function down(MonitorView $monitor, string $incidentId, CarbonImmutable $startedAt, string $failureKind, ?int $httpStatus): AlertMessage
    {
        return new AlertMessage(
            DeliveryKind::Down,
            $this->text('alerting.down.title', ['name' => $monitor->name]),
            $monitor->url,
            $this->settings->presentation->incidentLink($incidentId),
            $this->text('alerting.link'),
            $this->settings->presentation->colorFor(DeliveryKind::Down),
            $startedAt,
            AlertField::text($this->text('alerting.labels.cause'), $this->translated(AlertTextRules::cause($failureKind, $httpStatus))),
            $this->timeField('alerting.labels.started', $startedAt),
        );
    }

    public function up(MonitorView $monitor, string $incidentId, CarbonImmutable $resolvedAt, int $durationSeconds): AlertMessage
    {
        return new AlertMessage(
            DeliveryKind::Up,
            $this->text('alerting.up.title', ['name' => $monitor->name]),
            $this->text('alerting.up.downtime', ['duration' => $this->duration($durationSeconds)]),
            $this->settings->presentation->incidentLink($incidentId),
            $this->text('alerting.link'),
            $this->settings->presentation->colorFor(DeliveryKind::Up),
            $resolvedAt,
            AlertField::text($this->text('alerting.labels.url'), $monitor->url),
            $this->timeField('alerting.labels.back_up', $resolvedAt),
        );
    }

    public function reminder(MonitorView $monitor, string $incidentId, CarbonImmutable $startedAt, ?string $failureKind, ?int $httpStatus, CarbonImmutable $now): AlertMessage
    {
        return new AlertMessage(
            DeliveryKind::Reminder,
            $this->text('alerting.reminder.title', ['name' => $monitor->name]),
            $monitor->url,
            $this->settings->presentation->incidentLink($incidentId),
            $this->text('alerting.link'),
            $this->settings->presentation->colorFor(DeliveryKind::Reminder),
            $now,
            AlertField::text($this->text('alerting.labels.cause'), $this->translated(AlertTextRules::cause($failureKind ?? AlertTextRules::UNKNOWN_CAUSE, $httpStatus))),
            AlertField::text($this->text('alerting.labels.down_for'), $this->duration($now->getTimestamp() - $startedAt->getTimestamp())),
        );
    }

    public function certificateExpiring(MonitorView $monitor, CarbonImmutable $expiresAt, int $days, CarbonImmutable $detectedAt): AlertMessage
    {
        $title = $this->translator->choice('alerting.certificate.title', $days, ['name' => $monitor->name, 'days' => $days]);

        return new AlertMessage(
            DeliveryKind::CertificateExpiring,
            $title,
            $monitor->url,
            $this->settings->presentation->monitorLink($monitor->id),
            $this->text('alerting.monitor_link'),
            $this->settings->presentation->colorFor(DeliveryKind::CertificateExpiring),
            $detectedAt,
            $this->timeField('alerting.labels.expires', $expiresAt),
        );
    }

    public function test(string $channelName, CarbonImmutable $sentAt): AlertMessage
    {
        return new AlertMessage(
            DeliveryKind::Test,
            $this->text('alerting.test.title'),
            $this->text('alerting.test.summary', ['channel' => $channelName]),
            null,
            $this->text('alerting.link'),
            $this->settings->presentation->colorFor(DeliveryKind::Test),
            $sentAt,
        );
    }

    private function duration(int $seconds): string
    {
        return implode(' ', array_map(
            fn (mixed $part): string => $part instanceof TranslatableText ? $this->translated($part) : '',
            AlertTextRules::durationParts($seconds),
        ));
    }

    private function timeField(string $labelKey, CarbonImmutable $time): AlertField
    {
        return AlertField::time($this->text($labelKey), $time, $this->text('alerting.time_utc', ['time' => $time->utc()->format(self::UTC_FORMAT)]));
    }

    private function translated(TranslatableText $text): string
    {
        $translated = $this->text($text->key, $text->parameters);
        if ($translated === $text->key && $text->fallbackKey !== null) {
            return $this->text($text->fallbackKey, $text->parameters);
        }

        return $translated;
    }

    private function text(string $key, array $parameters = []): string
    {
        $text = $this->translator->get($key, $parameters);

        return is_string($text) ? $text : $key;
    }
}
