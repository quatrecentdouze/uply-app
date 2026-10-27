<?php

declare(strict_types=1);

namespace App\Incidents\Storage;

use App\Incidents\Contracts\OpenIncident;
use App\Incidents\Contracts\OpenIncidents;
use App\Incidents\Models\Incident;

final class DatabaseOpenIncidents implements OpenIncidents
{
    public function all(): array
    {
        $open = [];
        foreach (Incident::query()->whereNull('resolved_at')->orderBy('started_at')->orderBy('id')->get() as $incident) {
            $open[] = new OpenIncident(
                $incident->id,
                $incident->organization_id,
                $incident->monitor_id,
                $incident->startedAt(),
                $incident->cause_failure_kind,
                $incident->cause_http_status,
            );
        }

        return $open;
    }
}
