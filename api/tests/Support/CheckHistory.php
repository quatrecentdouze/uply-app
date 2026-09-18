<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Monitoring\Storage\CheckStorageMaintenance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class CheckHistory
{
    private const int PARTITION_DAYS_PER_RUN = 3;

    public static function keepDaysBefore(CarbonImmutable $now, int $days): void
    {
        $maintenance = app(CheckStorageMaintenance::class);
        for ($offset = $days; $offset > 0; $offset -= self::PARTITION_DAYS_PER_RUN) {
            $maintenance->run($now->subDays($offset));
        }
        $maintenance->run($now);
    }

    public static function result(string $monitorId, CarbonImmutable $checkedAt, bool $up, ?int $responseMs = 100, array $timings = []): void
    {
        DB::table('check_results')->insert([
            'monitor_id' => $monitorId,
            'checked_at' => $checkedAt,
            'outcome' => $up ? 'up' : 'down',
            'failure_kind' => $up ? null : ($responseMs === null ? 'timeout' : 'http_status'),
            'http_status' => $responseMs === null ? null : ($up ? 200 : 500),
            'response_ms' => $responseMs,
            'dns_ms' => $timings['dns_ms'] ?? null,
            'connect_ms' => $timings['connect_ms'] ?? null,
            'tls_ms' => $timings['tls_ms'] ?? null,
            'ttfb_ms' => $timings['ttfb_ms'] ?? null,
            'error_message' => $up ? null : 'HTTP 500',
        ]);
    }

    public static function hour(string $monitorId, CarbonImmutable $hourStart, int $total, int $up, int $responseMsSum = 0, int $responseMsCount = 0, ?int $min = null, ?int $max = null): void
    {
        DB::table('monitor_hourly_stats')->insert([
            'monitor_id' => $monitorId,
            'hour_start' => $hourStart,
            'checks_total' => $total,
            'checks_up' => $up,
            'response_ms_sum' => $responseMsSum,
            'response_ms_count' => $responseMsCount,
            'response_ms_min' => $min,
            'response_ms_max' => $max,
        ]);
    }
}
