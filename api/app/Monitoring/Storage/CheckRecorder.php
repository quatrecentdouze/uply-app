<?php

declare(strict_types=1);

namespace App\Monitoring\Storage;

use App\Maintenance\Contracts\MaintenanceSchedule;
use App\Monitoring\Enums\CheckOutcome;
use App\Monitoring\Enums\StateTransition;
use App\Monitoring\Models\Monitor;
use App\Monitoring\Rules\CertificateExpiryRules;
use App\Monitoring\Rules\CheckScheduleRules;
use App\Monitoring\Rules\FlappingRules;
use App\Monitoring\Rules\MonitorStateRules;
use App\Monitoring\Settings\MonitoringSettings;
use App\Monitoring\Values\CertificateWatch;
use App\Monitoring\Values\CheckEffects;
use App\Monitoring\Values\CompletedCheck;
use App\Monitoring\Values\FlappingStep;
use App\Monitoring\Values\StateAdvance;
use Illuminate\Database\ConnectionInterface;

final readonly class CheckRecorder
{
    public function __construct(
        private ConnectionInterface $database,
        private CheckAnnouncer $announcer,
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
            $effects = $this->maintenance->covers($monitor->id, $check->checkedAt)
                ? $this->frozenEffects($monitor)
                : $this->effects($monitor, $check);
            $nextCheckAt = CheckScheduleRules::nextCheckAt($check->scheduledAt, $check->checkedAt, $monitor->interval_seconds, $this->settings->confirmation->recheckDelaySeconds, $effects->advance->awaitsConfirmation);

            $this->insertResult($check, $effects->inMaintenance);
            $this->addToHourlyStats($check, $effects->inMaintenance);
            $monitor->applyCheck($effects->advance->snapshot, $check->checkedAt, $check->result->responseMs(), $nextCheckAt);
            $monitor->applyFlapping($effects->flapping->state);
            if ($effects->certificate !== null) {
                $monitor->watchCertificate($effects->certificate);
            }
            $monitor->save();
            $this->announcer->announce($monitor, $check, $effects);

            return true;
        });
    }

    private function frozenEffects(Monitor $monitor): CheckEffects
    {
        return new CheckEffects(StateAdvance::unchanged($monitor->stateSnapshot()), true, FlappingStep::unchanged($monitor->flappingState()), null);
    }

    private function effects(Monitor $monitor, CompletedCheck $check): CheckEffects
    {
        $advance = MonitorStateRules::advance($monitor->stateSnapshot(), $check->verdict, $monitor->confirmationThresholds(), $check->checkedAt);
        $flapping = $this->settings->flapping;
        $step = $advance->transition === StateTransition::None
            ? FlappingRules::afterCheck($monitor->flappingState(), $check->checkedAt, $flapping->stableMinutes)
            : FlappingRules::afterTransition($monitor->flappingState(), $check->checkedAt, $flapping->changes, $flapping->windowMinutes);

        return new CheckEffects($advance, false, $step, $this->watchCertificate($monitor, $check));
    }

    private function watchCertificate(Monitor $monitor, CompletedCheck $check): ?CertificateWatch
    {
        $seenExpiry = $check->result->tlsExpiresAt;
        if ($seenExpiry === null) {
            return null;
        }

        return CertificateExpiryRules::watch($monitor->tls_expires_at, $monitor->tls_alerted_days, $seenExpiry, $check->checkedAt, $this->settings->certificate->expiryAlertDays);
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
}
