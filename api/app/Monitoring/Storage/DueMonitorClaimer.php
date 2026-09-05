<?php

declare(strict_types=1);

namespace App\Monitoring\Storage;

use App\Monitoring\Values\ClaimedCheck;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;

final readonly class DueMonitorClaimer
{
    public function __construct(
        private ConnectionInterface $database,
    ) {}

    public function claim(CarbonImmutable $now, int $limit): array
    {
        $rows = $this->database->select(<<<'SQL'
            UPDATE monitors AS monitor
            SET next_check_at = due.next_check_at + make_interval(secs => monitor.interval_seconds)
            FROM (
                SELECT id, next_check_at
                FROM monitors
                WHERE NOT is_paused AND next_check_at <= ?
                ORDER BY next_check_at
                LIMIT ?
                FOR UPDATE SKIP LOCKED
            ) AS due
            WHERE monitor.id = due.id
            RETURNING monitor.id AS monitor_id, due.next_check_at AS scheduled_at
            SQL, [$now->utc(), $limit], false);

        $claimed = [];
        foreach ($rows as $row) {
            if (is_object($row) && isset($row->monitor_id, $row->scheduled_at) && is_string($row->monitor_id) && is_string($row->scheduled_at)) {
                $claimed[] = new ClaimedCheck($row->monitor_id, CarbonImmutable::parse($row->scheduled_at)->utc());
            }
        }

        return $claimed;
    }
}
