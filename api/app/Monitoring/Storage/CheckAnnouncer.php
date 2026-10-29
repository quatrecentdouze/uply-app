<?php

declare(strict_types=1);

namespace App\Monitoring\Storage;

use App\Monitoring\Enums\FailureKind;
use App\Monitoring\Enums\FlappingTurn;
use App\Monitoring\Enums\StateTransition;
use App\Monitoring\Events\CertificateExpiringEvent;
use App\Monitoring\Events\CheckCompletedEvent;
use App\Monitoring\Events\MonitorFlappingEndedEvent;
use App\Monitoring\Events\MonitorFlappingStartedEvent;
use App\Monitoring\Events\MonitorRecoveredEvent;
use App\Monitoring\Events\MonitorWentDownEvent;
use App\Monitoring\Models\Monitor;
use App\Monitoring\Settings\MonitoringSettings;
use App\Monitoring\Values\CheckEffects;
use App\Monitoring\Values\CompletedCheck;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class CheckAnnouncer
{
    public function __construct(
        private Dispatcher $events,
        private MonitoringSettings $settings,
    ) {}

    public function announce(Monitor $monitor, CompletedCheck $check, CheckEffects $effects): void
    {
        $this->checkCompleted($monitor, $check, $effects);
        $this->transition($monitor, $check, $effects);
        $this->flapping($monitor, $check, $effects);
        $this->certificate($monitor, $check, $effects);
    }

    private function checkCompleted(Monitor $monitor, CompletedCheck $check, CheckEffects $effects): void
    {
        $this->events->dispatch(new CheckCompletedEvent(
            monitorId: $monitor->id,
            organizationId: $monitor->organization_id,
            checkedAt: $check->checkedAt,
            outcome: $check->verdict->outcome,
            failureKind: $check->verdict->failureKind,
            httpStatus: $check->result->httpStatus,
            responseMs: $check->result->responseMs(),
            state: $effects->advance->snapshot->state,
            previousState: $effects->advance->previousState,
            inMaintenance: $effects->inMaintenance,
        ));
    }

    private function transition(Monitor $monitor, CompletedCheck $check, CheckEffects $effects): void
    {
        $snapshot = $effects->advance->snapshot;
        $flapping = $effects->flapping->state->isFlapping();
        if ($effects->advance->transition === StateTransition::WentDown) {
            $this->events->dispatch(new MonitorWentDownEvent(
                monitorId: $monitor->id,
                organizationId: $monitor->organization_id,
                downSince: $snapshot->streakStartedAt ?? $check->checkedAt,
                confirmedAt: $check->checkedAt,
                failureKind: $check->verdict->failureKind ?? FailureKind::Unknown,
                httpStatus: $check->result->httpStatus,
                errorMessage: $check->errorMessage(),
                flapping: $flapping,
            ));
        }
        if ($effects->advance->transition === StateTransition::Recovered) {
            $this->events->dispatch(new MonitorRecoveredEvent(
                monitorId: $monitor->id,
                organizationId: $monitor->organization_id,
                upSince: $snapshot->streakStartedAt ?? $check->checkedAt,
                confirmedAt: $check->checkedAt,
                flapping: $flapping,
            ));
        }
    }

    private function flapping(Monitor $monitor, CompletedCheck $check, CheckEffects $effects): void
    {
        $step = $effects->flapping;
        $since = $step->episodeStartedAt;
        if ($step->turn === FlappingTurn::Started && $since !== null) {
            $this->events->dispatch(new MonitorFlappingStartedEvent(
                monitorId: $monitor->id,
                organizationId: $monitor->organization_id,
                flappingSince: $since,
                changes: $step->state->changeCount(),
                windowMinutes: $this->settings->flapping->windowMinutes,
            ));
        }
        if ($step->turn === FlappingTurn::Ended && $since !== null) {
            $this->events->dispatch(new MonitorFlappingEndedEvent(
                monitorId: $monitor->id,
                organizationId: $monitor->organization_id,
                flappingSince: $since,
                endedAt: $check->checkedAt,
                state: $effects->advance->snapshot->state,
                stableMinutes: $this->settings->flapping->stableMinutes,
            ));
        }
    }

    private function certificate(Monitor $monitor, CompletedCheck $check, CheckEffects $effects): void
    {
        $certificate = $effects->certificate;
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
}
