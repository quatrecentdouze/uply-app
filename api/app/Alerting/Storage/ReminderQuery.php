<?php

declare(strict_types=1);

namespace App\Alerting\Storage;

use App\Alerting\Enums\DeliveryKind;
use App\Alerting\Models\AlertChannel;
use App\Alerting\Values\ReminderHistory;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

final readonly class ReminderQuery
{
    public function __construct(private ConnectionInterface $database) {}

    public function channelsOf(string $monitorId): array
    {
        return AlertChannel::query()
            ->where('is_enabled', true)
            ->whereNotNull('reminder_interval_minutes')
            ->whereIn('id', fn (Builder $links): Builder => $links->select('alert_channel_id')->from('alert_channel_monitor')->where('monitor_id', $monitorId))
            ->orderBy('id')
            ->get()
            ->all();
    }

    public function histories(string $incidentId, array $channelIds): array
    {
        $ids = array_values(array_filter($channelIds, 'is_string'));
        if ($ids === []) {
            return [];
        }
        $rows = $this->database->table('alert_deliveries')
            ->selectRaw('alert_channel_id, max(created_at) AS last_alert_at, coalesce(max(reminder_number), 0) AS reminders_sent')
            ->where('incident_id', $incidentId)
            ->whereIn('alert_channel_id', $ids)
            ->whereIn('kind', [DeliveryKind::Down->value, DeliveryKind::Reminder->value])
            ->groupBy('alert_channel_id')
            ->get();

        $histories = [];
        foreach ($rows as $row) {
            if (is_string($row->alert_channel_id) && is_string($row->last_alert_at) && is_int($row->reminders_sent)) {
                $histories[$row->alert_channel_id] = new ReminderHistory(CarbonImmutable::parse($row->last_alert_at)->utc(), $row->reminders_sent);
            }
        }

        return $histories;
    }
}
