<?php

declare(strict_types=1);

namespace App\Monitoring\Storage;

use App\Maintenance\Contracts\MaintenanceSchedule;
use App\Monitoring\Enums\CheckOutcome;
use App\Monitoring\Enums\FailureKind;
use App\Monitoring\Enums\StateTransition;
use App\Monitoring\Events\CertificateExpiringEvent;
use App\Monitoring\Events\CheckCompletedEvent;
use App\Monitoring\Events\MonitorRecoveredEvent;
use App\Monitoring\Events\MonitorWentDownEvent;
use App\Monitoring\Models\Monitor;
use App\Monitoring\Rules\CertificateExpiryRules;
use App\Monitoring\Rules\CheckScheduleRules;
use App\Monitoring\Rules\MonitorStateRules;
use App\Monitoring\Settings\MonitoringSettings;
use App\Monitoring\Values\CertificateWatch;
use App\Monitoring\Values\CompletedCheck;
use App\Monitoring\Values\StateAdvance;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

final readonly class CheckRecorder
{
    public function __construct(
        private ConnectionInterface $database,
        private Dispatcher $events,
        private MonitoringSettings $settings,
        private MaintenanceSchedule $maintenance,
    ) {}

    public function record(CompletedCheck $check): bool
    {
        return $this->database->transaction(function () use ($check): bool {
            $monitor = Monitor::query()->whereKey($check->monitorId)->lockForUpdate()->first();
            if ($monitor === null || $monitor->is_paused || ! CheckScheduleRules::slotIsOpen($monitor->last_checked_at, $check->scheduledAt)) {
                return false;
            }
            $confirmation = $this->settings->confirmation;
            $inMaintenance = $this->maintenance->covers($monitor->id, $check->checkedAt);
            $advance = $inMaintenance
                ? StateAdvance::unchanged($monitor->stateSnapshot())
                : MonitorStateRules::advance($monitor->stateSnapshot(), $check->verdict, $monitor->confirmationThresholds(), $check->checkedAt);
            $nextCheckAt = CheckScheduleRules::nextCheckAt($check->scheduledAt, $check->checkedAt, $monitor->interval_seconds, $confirmation->recheckDelaySeconds, $advance->awaitsConfirmation);

            $certificate = $inMaintenance ? null : $this->watchCertificate($monitor, $check);

            $this->insertResult($check, $inMaintenance);
            $this->addToHourlyStats($check, $inMaintenance);
            $monitor->applyCheck($advance->snapshot, $check->checkedAt, $check->result->responseMs(), $nextCheckAt);
            if ($certificate !== null) {
                $monitor->watchCertificate($certificate);
            }
            $monitor->save();
            $this->announce($monitor, $check, $advance, $inMaintenance);
            $this->announceCertificate($monitor, $check, $certificate);

            return true;
        });
    }

    private function watchCertificate(Monitor $monitor, CompletedCheck $check): ?CertificateWatch
    {
        $seenExpiry = $check->result->tlsExpiresAt;
        if ($seenExpiry === null) {
            return null;
        }

        return CertificateExpiryRules::watch($monitor->tls_expires_at, $monitor->tls_alerted_days, $seenExpiry, $check->checkedAt, $this->settings->certificate->expiryAlertDays);
    }

    private function announceCertificate(Monitor $monitor, CompletedCheck $check, ?CertificateWatch $certificate): void
    {
        if ($certificate?->alertDays === null) {
            return;
        }
        $this->events->dispatch(new CertificateExpiringEvent(
            monitorId: $monitor->id,
            organizationId: $monitor->organization_id,
            expiresAt: $certificate->expiresAt,
            days: $certificate->alertDays,
            detectedAt: $check->checkedAt,
        ));
    }

    private function insertResult(CompletedCheck $check, bool $inMaintenance): void
    {
        $timings = $check->result->timings;
        $this->database->table('check_results')->insert([
            'monitor_id' => $check->monitorId,
            'checked_at' => $check->checkedAt,
            'outcome' => $check->verdict->outcome->value,
            'failure_kind' => $check->verdict->failureKind?->value,
            'http_status' => $check->result->httpStatus,
            'response_ms' => $check->result->responseMs(),
            'dns_ms' => $timings->dnsMs,
            'connect_ms' => $timings->connectMs,
            'tls_ms' => $timings->tlsMs,
            'ttfb_ms' => $timings->ttfbMs,
            'error_message' => $check->errorMessage(),
            'in_maintenance' => $inMaintenance,
        ]);
    }

    private function addToHourlyStats(CompletedCheck $check, bool $inMaintenance): void
    {
        $responseMs = $inMaintenance ? null : $check->result->responseMs();
        $this->database->statement(<<<'SQL'
            INSERT INTO monitor_hourly_stats AS stats
                (monitor_id, hour_start, checks_total, checks_up, response_ms_sum, response_ms_count, response_ms_min, response_ms_max, checks_maintenance)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT (monitor_id, hour_start) DO UPDATE SET
                checks_total = stats.checks_total + EXCLUDED.checks_total,
                checks_up = stats.checks_up + EXCLUDED.checks_up,
                response_ms_sum = stats.response_ms_sum + EXCLUDED.response_ms_sum,
                response_ms_count = stats.response_ms_count + EXCLUDED.response_ms_count,
                response_ms_min = LEAST(stats.response_ms_min, EXCLUDED.response_ms_min),
                response_ms_max = GREATEST(stats.response_ms_max, EXCLUDED.response_ms_max),
                checks_maintenance = stats.checks_maintenance + EXCLUDED.checks_maintenance
            SQL, [
            $check->monitorId,
            $check->checkedAt->startOfHour(),
            $inMaintenance ? 0 : 1,
            ! $inMaintenance && $check->verdict->outcome === CheckOutcome::Up ? 1 : 0,
            $responseMs ?? 0,
            $responseMs === null ? 0 : 1,
            $responseMs,
            $responseMs,
            $inMaintenance ? 1 : 0,
        ]);
    }

    private function announce(Monitor $monitor, CompletedCheck $check, StateAdvance $advance, bool $inMaintenance): void
    {
        $snapshot = $advance->snapshot;
        $this->events->dispatch(new CheckCompletedEvent(
            monitorId: $monitor->id,
            organizationId: $monitor->organization_id,
            checkedAt: $check->checkedAt,
            outcome: $check->verdict->outcome,
            failureKind: $check->verdict->failureKind,
            httpStatus: $check->result->httpStatus,
            responseMs: $check->result->responseMs(),
            state: $snapshot->state,
            previousState: $advance->previousState,
            inMaintenance: $inMaintenance,
        ));

        if ($advance->transition === StateTransition::WentDown) {
            $this->events->dispatch(new MonitorWentDownEvent(
                monitorId: $monitor->id,
                organizationId: $monitor->organization_id,
                downSince: $snapshot->streakStartedAt ?? $check->checkedAt,
                confirmedAt: $check->checkedAt,
                failureKind: $check->verdict->failureKind ?? FailureKind::Unknown,
                httpStatus: $check->result->httpStatus,
                errorMessage: $check->errorMessage(),
            ));
        }
        if ($advance->transition === StateTransition::Recovered) {
            $this->events->dispatch(new MonitorRecoveredEvent(
                monitorId: $monitor->id,
                organizationId: $monitor->organization_id,
                upSince: $snapshot->streakStartedAt ?? $check->checkedAt,
                confirmedAt: $check->checkedAt,
            ));
        }
    }
}
