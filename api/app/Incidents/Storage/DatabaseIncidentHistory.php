<?php

declare(strict_types=1);

namespace App\Incidents\Storage;

use App\Incidents\Contracts\IncidentHistory;
use App\Incidents\Contracts\IncidentSummary;
use App\Incidents\Models\Incident;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class DatabaseIncidentHistory implements IncidentHistory
{
    public function since(array $monitorIds, CarbonImmutable $since, int $limit): array
    {
        $ids = array_values(array_unique(array_filter($monitorIds, 'is_string')));
        if ($ids === [] || $limit < 1) {
            return [];
        }
        $incidents = Incident::query()
            ->whereIn('monitor_id', $ids)
            ->where(fn (Builder $overlapping): Builder => $overlapping->whereNull('resolved_at')->orWhere('resolved_at', '>=', $since))
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $summaries = [];
        foreach ($incidents as $incident) {
            $summaries[] = new IncidentSummary($incident->id, $incident->monitor_id, $incident->startedAt(), $incident->resolved_at, $incident->durationSeconds());
        }

        return $summaries;
    }
}
