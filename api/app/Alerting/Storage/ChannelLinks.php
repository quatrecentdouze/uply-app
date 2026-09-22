<?php

declare(strict_types=1);

namespace App\Alerting\Storage;

use App\Alerting\Models\AlertChannel;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;

final readonly class ChannelLinks
{
    private const string TABLE = 'alert_channel_monitor';

    public function __construct(private ConnectionInterface $database) {}

    public function channelsOf(string $monitorId): Collection
    {
        return AlertChannel::query()
            ->whereIn('id', fn (Builder $links): Builder => $links->select('alert_channel_id')->from(self::TABLE)->where('monitor_id', $monitorId))
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    public function replace(string $monitorId, array $channelIds): void
    {
        $this->database->transaction(function () use ($monitorId, $channelIds): void {
            $this->database->table(self::TABLE)->where('monitor_id', $monitorId)->delete();
            $rows = [];
            foreach (array_unique(array_filter($channelIds, 'is_string')) as $channelId) {
                $rows[] = ['alert_channel_id' => $channelId, 'monitor_id' => $monitorId];
            }
            $this->database->table(self::TABLE)->insert($rows);
        });
    }
}
