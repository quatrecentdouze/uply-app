<?php

declare(strict_types=1);

namespace App\Monitoring\Storage;

use App\Monitoring\Contracts\UptimeQuery;
use App\Monitoring\Rules\UptimeRules;
use App\Monitoring\Values\CheckTotals;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;

final readonly class DatabaseUptimeQuery implements UptimeQuery
{
    public function __construct(private ConnectionInterface $database) {}

    public function daily(array $monitorIds, CarbonImmutable $lastDay, int $days): array
    {
        $ids = array_values(array_unique(array_filter($monitorIds, 'is_string')));
        if ($ids === []) {
            return [];
        }
        $first = UptimeRules::firstDay($lastDay, $days);
        $rows = $this->database->table('monitor_hourly_stats')
            ->selectRaw("monitor_id, to_char(date_trunc('day', hour_start AT TIME ZONE 'UTC'), 'YYYY-MM-DD') AS day, sum(checks_total)::bigint AS checks_total, sum(checks_up)::bigint AS checks_up")
            ->whereIn('monitor_id', $ids)
            ->where('hour_start', '>=', $first)
            ->where('hour_start', '<', $lastDay->utc()->startOfDay()->addDay())
            ->groupBy('monitor_id', 'day')
            ->get();

        $totals = [];
        foreach ($rows as $row) {
            if (is_string($row->monitor_id) && is_string($row->day) && is_int($row->checks_total) && is_int($row->checks_up)) {
                $totals[$row->monitor_id][$row->day] = new CheckTotals($row->checks_total, $row->checks_up, 0, 0, null, null);
            }
        }

        $histories = [];
        foreach ($ids as $id) {
            $histories[$id] = UptimeRules::history($lastDay, $days, $totals[$id] ?? []);
        }

        return $histories;
    }
}
