<?php

declare(strict_types=1);

namespace App\Monitoring\Storage;

use App\Monitoring\Enums\CheckOutcome;
use App\Monitoring\Rules\UptimeRules;
use App\Monitoring\Values\BucketPlan;
use App\Monitoring\Values\CheckTotals;
use App\Monitoring\Values\HourlyTotals;
use App\Monitoring\Values\ProbeTimings;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use stdClass;

final readonly class StatsQuery
{
    private const string TOTALS = 'sum(checks_total)::bigint AS checks_total, sum(checks_up)::bigint AS checks_up, '
        .'sum(response_ms_sum)::bigint AS response_ms_sum, sum(response_ms_count)::bigint AS response_ms_count, '
        .'min(response_ms_min) AS response_ms_min, max(response_ms_max) AS response_ms_max';

    public function __construct(private ConnectionInterface $database) {}

    public function hourly(array $monitorIds, BucketPlan $plan): array
    {
        $rows = $this->database->table('monitor_hourly_stats')
            ->selectRaw('hour_start, '.self::TOTALS)
            ->whereIn('monitor_id', $monitorIds)
            ->where('hour_start', '>=', $plan->firstStart)
            ->where('hour_start', '<', $plan->end())
            ->groupBy('hour_start')
            ->get();

        $hours = [];
        foreach ($rows as $row) {
            if (is_string($row->hour_start)) {
                $hours[] = new HourlyTotals(CarbonImmutable::parse($row->hour_start)->utc(), self::totals($row));
            }
        }

        return $hours;
    }

    public function raw(array $monitorIds, CarbonImmutable $after, CarbonImmutable $until): CheckTotals
    {
        $row = $this->database->table('check_results')
            ->selectRaw(
                'count(*) AS checks_total, count(*) FILTER (WHERE outcome = ?) AS checks_up, coalesce(sum(response_ms), 0)::bigint AS response_ms_sum, '
                .'count(response_ms) AS response_ms_count, min(response_ms) AS response_ms_min, max(response_ms) AS response_ms_max',
                [CheckOutcome::Up->value],
            )
            ->whereIn('monitor_id', $monitorIds)
            ->where('in_maintenance', false)
            ->where('checked_at', '>', $after)
            ->where('checked_at', '<=', $until)
            ->first();

        return $row instanceof stdClass ? self::totals($row) : CheckTotals::none();
    }

    public function timings(string $monitorId, CarbonImmutable $after, CarbonImmutable $until): ProbeTimings
    {
        $row = $this->database->table('check_results')
            ->selectRaw(
                'coalesce(sum(dns_ms), 0)::bigint AS dns_sum, count(dns_ms) AS dns_count, coalesce(sum(connect_ms), 0)::bigint AS connect_sum, count(connect_ms) AS connect_count, '
                .'coalesce(sum(tls_ms), 0)::bigint AS tls_sum, count(tls_ms) AS tls_count, coalesce(sum(ttfb_ms), 0)::bigint AS ttfb_sum, count(ttfb_ms) AS ttfb_count',
            )
            ->where('monitor_id', $monitorId)
            ->where('in_maintenance', false)
            ->where('checked_at', '>', $after)
            ->where('checked_at', '<=', $until)
            ->first();
        if (! $row instanceof stdClass) {
            return ProbeTimings::none();
        }

        return new ProbeTimings(
            UptimeRules::averageMs(self::integer($row->dns_sum), self::integer($row->dns_count)),
            UptimeRules::averageMs(self::integer($row->connect_sum), self::integer($row->connect_count)),
            UptimeRules::averageMs(self::integer($row->tls_sum), self::integer($row->tls_count)),
            UptimeRules::averageMs(self::integer($row->ttfb_sum), self::integer($row->ttfb_count)),
        );
    }

    private static function totals(stdClass $row): CheckTotals
    {
        return new CheckTotals(
            self::integer($row->checks_total),
            self::integer($row->checks_up),
            self::integer($row->response_ms_sum),
            self::integer($row->response_ms_count),
            is_int($row->response_ms_min) ? $row->response_ms_min : null,
            is_int($row->response_ms_max) ? $row->response_ms_max : null,
        );
    }

    private static function integer(mixed $value): int
    {
        return is_int($value) ? $value : 0;
    }
}
