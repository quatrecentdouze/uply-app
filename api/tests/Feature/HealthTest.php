<?php

declare(strict_types=1);

it('answers the health route', function (): void {
    $this->get('/up')->assertOk();
});

it('runs on the postgres test database', function (): void {
    expect(config('database.default'))->toBe('pgsql')
        ->and(config('database.connections.pgsql.database'))->toBe('uply_test');
});
