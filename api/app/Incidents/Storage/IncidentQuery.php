<?php

declare(strict_types=1);

namespace App\Incidents\Storage;

use App\Incidents\Enums\IncidentState;
use App\Incidents\Models\Incident;
use App\Incidents\Values\MonitorNames;
use App\Monitoring\Contracts\MonitorDirectory;
use App\Monitoring\Contracts\MonitorView;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class IncidentQuery
{
    public function __construct(private MonitorDirectory $monitors) {}

    public function page(string $organizationId, ?IncidentState $state, ?string $monitorId, int $perPage): LengthAwarePaginator
    {
        $query = Incident::query()->where('organization_id', $organizationId);
        if ($state === IncidentState::Open) {
            $query->whereNull('resolved_at');
        }
        if ($state === IncidentState::Resolved) {
            $query->whereNotNull('resolved_at');
        }
        if ($monitorId !== null) {
            $query->where('monitor_id', $monitorId);
        }

        return $query->orderByDesc('started_at')->orderByDesc('id')->paginate($perPage);
    }

    public function get(string $organizationId, string $incidentId): Incident
    {
        return Incident::query()->where('organization_id', $organizationId)->whereKey($incidentId)->firstOrFail();
    }

    public function monitorNames(array $incidents): MonitorNames
    {
        $monitorIds = [];
        foreach ($incidents as $incident) {
            if ($incident instanceof Incident) {
                $monitorIds[] = $incident->monitor_id;
            }
        }
        $names = [];
        foreach ($this->monitors->findMany(array_values(array_unique($monitorIds))) as $id => $view) {
            if ($view instanceof MonitorView) {
                $names[$id] = $view->name;
            }
        }

        return new MonitorNames($names);
    }
}
