<?php

declare(strict_types=1);

namespace App\Maintenance\Storage;

use App\Maintenance\Enums\MaintenanceState;
use App\Maintenance\Models\MaintenanceWindow;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;

final class MaintenanceWindowQuery
{
    public function page(string $organizationId, ?MaintenanceState $state, CarbonImmutable $now, int $perPage): LengthAwarePaginator
    {
        $query = MaintenanceWindow::query()->where('organization_id', $organizationId)->with('monitors');
        match ($state) {
            MaintenanceState::Upcoming => $query->where('starts_at', '>', $now)->orderBy('starts_at'),
            MaintenanceState::Active => $query->where('starts_at', '<=', $now)->where('ends_at', '>', $now)->orderBy('starts_at'),
            MaintenanceState::Ended => $query->where('ends_at', '<=', $now)->orderByDesc('ends_at'),
            null => $query
                ->orderByRaw('ends_at <= ?', [$now])
                ->orderByRaw('CASE WHEN ends_at > ? THEN starts_at END', [$now])
                ->orderByDesc('ends_at'),
        };

        return $query->orderBy('id')->paginate($perPage);
    }

    public function get(string $organizationId, string $windowId): MaintenanceWindow
    {
        return MaintenanceWindow::query()->where('organization_id', $organizationId)->whereKey($windowId)->with('monitors')->firstOrFail();
    }
}
