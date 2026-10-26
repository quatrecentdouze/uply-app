<?php

declare(strict_types=1);

namespace App\Monitoring\Storage;

use App\Monitoring\Enums\CheckOutcome;
use App\Monitoring\Enums\FailureKind;
use App\Monitoring\Values\CheckRecord;
use App\Monitoring\Values\ProbeTimings;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Pagination\AbstractCursorPaginator;
use stdClass;
use UnexpectedValueException;

final readonly class CheckResultQuery
{
    private const array COLUMNS = ['id', 'checked_at', 'outcome', 'failure_kind', 'http_status', 'response_ms', 'dns_ms', 'connect_ms', 'tls_ms', 'ttfb_ms', 'error_message', 'in_maintenance'];

    public function __construct(private ConnectionInterface $database) {}

    public function page(string $monitorId, CarbonImmutable $from, CarbonImmutable $to, int $perPage): AbstractCursorPaginator
    {
        $page = $this->database->table('check_results')
            ->where('monitor_id', $monitorId)
            ->where('checked_at', '>=', $from)
            ->where('checked_at', '<=', $to)
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->cursorPaginate($perPage, self::COLUMNS);

        return $page instanceof AbstractCursorPaginator ? $page : throw new UnexpectedValueException('the query builder gave no cursor paginator');
    }

    public static function record(stdClass $row): CheckRecord
    {
        return new CheckRecord(
            checkedAt: CarbonImmutable::parse(self::text($row->checked_at))->utc(),
            outcome: CheckOutcome::from(self::text($row->outcome)),
            failureKind: is_string($row->failure_kind) ? FailureKind::from($row->failure_kind) : null,
            httpStatus: self::number($row->http_status),
            responseMs: self::number($row->response_ms),
            timings: new ProbeTimings(self::number($row->dns_ms), self::number($row->connect_ms), self::number($row->tls_ms), self::number($row->ttfb_ms)),
            errorMessage: is_string($row->error_message) ? $row->error_message : null,
            inMaintenance: $row->in_maintenance === true,
        );
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : throw new UnexpectedValueException('a check result column is not text');
    }

    private static function number(mixed $value): ?int
    {
        return is_int($value) ? $value : null;
    }
}
