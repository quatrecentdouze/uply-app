<?php

declare(strict_types=1);

use App\Shared\Database\AdvisoryLock;
use Illuminate\Support\Facades\DB;

function triesLock(string $connection, string $scope, string $key): bool
{
    $row = DB::connection($connection)->selectOne('SELECT pg_try_advisory_xact_lock(hashtextextended(?, 0)) AS taken', [AdvisoryLock::name($scope, $key)]);

    return is_object($row) && $row->taken === true;
}

it('holds a named lock until the transaction ends', function (): void {
    config(['database.connections.other' => config('database.connections.pgsql')]);
    $other = DB::connection('other');

    try {
        (new AdvisoryLock(DB::connection()))->holdUntilCommit('monitors', 'org-1');
        $other->beginTransaction();
        $sameKey = triesLock('other', 'monitors', 'org-1');
        $otherKey = triesLock('other', 'monitors', 'org-2');
        $otherScope = triesLock('other', 'status-pages', 'org-1');
        $other->rollBack();
    } finally {
        $other->disconnect();
    }

    expect($sameKey)->toBeFalse()
        ->and($otherKey)->toBeTrue()
        ->and($otherScope)->toBeTrue();
});

it('refuses to lock outside a transaction', function (): void {
    config(['database.connections.other' => config('database.connections.pgsql')]);
    $other = DB::connection('other');

    try {
        expect(fn () => (new AdvisoryLock($other))->holdUntilCommit('monitors', 'org-1'))
            ->toThrow(LogicException::class, 'the monitors lock is only held inside a transaction');
    } finally {
        $other->disconnect();
    }
});
