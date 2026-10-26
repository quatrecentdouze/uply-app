<?php

declare(strict_types=1);

namespace App\Monitoring\Storage;

use App\Monitoring\Enums\CheckOutcome;
use App\Monitoring\Rules\UptimeRules;
use App\Monitoring\Values\UptimeByMonitor;
use App\Shared\Clock;
use Illuminate\Database\ConnectionInterface;

final readonly class RecentUptimeQuery
{
    public function __construct(
        private ConnectionInterface $database,
        private Clock $clock,
    ) {}

    public function lastDay(array $monitorIds): UptimeByMonitor
    {
        $ids = array_values(array_filter($monitorIds, 'is_string'));
        if ($ids === []) {
            return new UptimeByMonitor([]);
        }
        $now = $this->clock->now();
        $rows = $this->database->table('check_results')
            ->selectRaw('monitor_id, count(*) AS checks_total, count(*) FILTER (WHERE outcome = ?) AS checks_up', [CheckOutcome::Up->value])
            ->whereIn('monitor_id', $ids)
            ->where('in_maintenance', false)
            ->where('checked_at', '>', UptimeRules::rollingDayStart($now))
            ->where('checked_at', '<=', $now)
            ->groupBy('monitor_id')
            ->get();

        $percents = [];
        foreach ($rows as $row) {
            if (is_string($row->monitor_id) && is_int($row->checks_total) && is_int($row->checks_up)) {
                $percents[$row->monitor_id] = UptimeRules::percent($row->checks_up, $row->checks_total);
            }
        }

        return new UptimeByMonitor($percents);
    }
}
