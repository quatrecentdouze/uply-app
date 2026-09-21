<?php

declare(strict_types=1);

namespace App\Alerting\Storage;

use App\Alerting\Models\AlertChannel;
use Illuminate\Pagination\LengthAwarePaginator;

final class AlertChannelQuery
{
    public function page(string $organizationId, int $perPage): LengthAwarePaginator
    {
        return AlertChannel::query()->where('organization_id', $organizationId)->orderBy('name')->orderBy('id')->paginate($perPage);
    }

    public function get(string $organizationId, string $channelId): AlertChannel
    {
        return AlertChannel::query()->where('organization_id', $organizationId)->whereKey($channelId)->firstOrFail();
    }
}
