<?php

declare(strict_types=1);

namespace App\Incidents\Storage;

use App\Incidents\Enums\IncidentEventKind;
use App\Incidents\Events\IncidentOpenedEvent;
use App\Incidents\Events\IncidentResolvedEvent;
use App\Incidents\Models\Incident;
use App\Incidents\Models\IncidentEvent;
use App\Incidents\Rules\IncidentRules;
use App\Incidents\Values\IncidentOpening;
use App\Shared\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

final readonly class IncidentRecorder
{
    public function __construct(
        private ConnectionInterface $database,
        private Dispatcher $events,
        private Clock $clock,
    ) {}

    public function open(IncidentOpening $opening): void
    {
        $this->database->transaction(function () use ($opening): void {
            $now = $this->clock->now();
            $incidentId = (new Incident)->newUniqueId();
            $opened = Incident::query()->insertOrIgnore([
                'id' => $incidentId,
                'organization_id' => $opening->organizationId,
                'monitor_id' => $opening->monitorId,
                'started_at' => $opening->startedAt,
                'cause_failure_kind' => $opening->causeFailureKind,
                'cause_http_status' => $opening->causeHttpStatus,
                'cause_message' => $opening->causeMessage,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if ($opened === 0) {
                return;
            }
            IncidentEvent::query()->create([
                'incident_id' => $incidentId,
                'kind' => IncidentEventKind::Opened,
                'occurred_at' => $opening->startedAt,
                'data' => [
                    'failure_kind' => $opening->causeFailureKind,
                    'http_status' => $opening->causeHttpStatus,
                    'message' => $opening->causeMessage,
                ],
            ]);
            $this->events->dispatch(new IncidentOpenedEvent(
                $incidentId,
                $opening->organizationId,
                $opening->monitorId,
                $opening->startedAt,
                $opening->causeFailureKind,
                $opening->causeHttpStatus,
                $opening->monitorFlapping,
            ));
        });
    }

    public function recordAlert(string $incidentId, bool $sent, CarbonImmutable $occurredAt, array $data): void
    {
        if (! Incident::query()->whereKey($incidentId)->exists()) {
            return;
        }
        IncidentEvent::query()->create([
            'incident_id' => $incidentId,
            'kind' => $sent ? IncidentEventKind::AlertSent : IncidentEventKind::AlertFailed,
            'occurred_at' => $occurredAt,
            'data' => $data,
        ]);
    }

    public function resolve(string $monitorId, CarbonImmutable $recoveredAt, bool $monitorFlapping): void
    {
        $this->database->transaction(function () use ($monitorId, $recoveredAt, $monitorFlapping): void {
            $incident = Incident::query()->where('monitor_id', $monitorId)->whereNull('resolved_at')->lockForUpdate()->first();
            if ($incident === null) {
                return;
            }
            $startedAt = $incident->startedAt();
            $resolvedAt = IncidentRules::resolutionTime($startedAt, $recoveredAt);
            $durationSeconds = IncidentRules::durationSeconds($startedAt, $resolvedAt);
            $incident->resolved_at = $resolvedAt;
            $incident->save();
            IncidentEvent::query()->create([
                'incident_id' => $incident->id,
                'kind' => IncidentEventKind::Resolved,
                'occurred_at' => $resolvedAt,
                'data' => ['duration_seconds' => $durationSeconds],
            ]);
            $this->events->dispatch(new IncidentResolvedEvent(
                $incident->id,
                $incident->organization_id,
                $monitorId,
                $startedAt,
                $resolvedAt,
                $durationSeconds,
                $monitorFlapping,
            ));
        });
    }
}
