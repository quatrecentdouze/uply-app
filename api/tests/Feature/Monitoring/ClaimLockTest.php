<?php

declare(strict_types=1);

use App\Monitoring\Storage\DueMonitorClaimer;
use App\Monitoring\Values\ClaimedCheck;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('skips a monitor row locked by another dispatcher', function (): void {
    config([
        'database.connections.writer' => config('database.connections.pgsql'),
        'database.connections.locker' => config('database.connections.pgsql'),
    ]);
    $clock = freezeClock('2027-03-10 12:00:00');
    $writer = DB::connection('writer');
    $locker = DB::connection('locker');
    $writer->statement("SET lock_timeout = '5s'");
    $locker->statement("SET lock_timeout = '5s'");
    $organizationId = Str::lower((string) Str::ulid());
    $monitorId = Str::lower((string) Str::ulid());
    $claimIds = fn (array $claimed): array => array_map(fn (ClaimedCheck $check): string => $check->monitorId, $claimed);

    try {
        $writer->table('organizations')->insert(['id' => $organizationId, 'name' => 'Locked', 'slug' => "locked-{$organizationId}"]);
        $writer->table('monitors')->insert([
            'id' => $monitorId,
            'organization_id' => $organizationId,
            'name' => 'Locked monitor',
            'url' => 'https://locked.example.com/',
            'method' => 'get',
            'interval_seconds' => 60,
            'timeout_seconds' => 10,
            'accepted_status_codes' => '["200-299"]',
            'failure_threshold' => 2,
            'recovery_threshold' => 2,
            'keyword_match' => 'present',
            'next_check_at' => '2027-03-10 11:59:00+00',
        ]);
        $locker->beginTransaction();
        $locker->select('SELECT id FROM monitors WHERE id = ? FOR UPDATE', [$monitorId]);

        $whileLocked = (new DueMonitorClaimer(DB::connection()))->claim($clock->now(), 500);
        $locker->rollBack();
        $afterRelease = (new DueMonitorClaimer($writer))->claim($clock->now(), 500);
    } finally {
        $writer->table('organizations')->where('id', $organizationId)->delete();
        $writer->disconnect();
        $locker->disconnect();
    }

    expect($claimIds($whileLocked))->not->toContain($monitorId)
        ->and($claimIds($afterRelease))->toBe([$monitorId]);
});
