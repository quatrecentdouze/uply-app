<?php

declare(strict_types=1);

namespace App\Monitoring\Storage;

use App\Monitoring\Contracts\MonitorStatus;
use App\Monitoring\Models\Monitor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class MonitorQuery
{
    public function page(string $organizationId, ?MonitorStatus $status, ?string $search, int $perPage): LengthAwarePaginator
    {
        $query = Monitor::query()->where('organization_id', $organizationId);
        if ($status !== null) {
            $this->withStatus($query, $status);
        }
        if ($search !== null) {
            $pattern = '%'.addcslashes($search, '\\%_').'%';
            $query->where(fn (Builder $matches): Builder => $matches->whereLike('name', $pattern)->orWhereLike('url', $pattern));
        }

        return $query->orderBy('name')->orderBy('id')->paginate($perPage);
    }

    public static function idsOf(LengthAwarePaginator $page): array
    {
        $ids = [];
        foreach ($page->items() as $monitor) {
            if ($monitor instanceof Monitor) {
                $ids[] = $monitor->id;
            }
        }

        return $ids;
    }

    public function get(string $organizationId, string $monitorId): Monitor
    {
        return Monitor::query()->where('organization_id', $organizationId)->whereKey($monitorId)->firstOrFail();
    }

    private function withStatus(Builder $query, MonitorStatus $status): void
    {
        if ($status === MonitorStatus::Paused) {
            $query->where('is_paused', true);

            return;
        }
        $query->where('is_paused', false)->where('state', $status->value);
    }
}
