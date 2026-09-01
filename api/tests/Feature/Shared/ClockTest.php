<?php

declare(strict_types=1);

use App\Shared\Clock;
use App\Shared\SystemClock;

it('binds the system clock in utc', function (): void {
    $clock = app(Clock::class);

    expect($clock)->toBeInstanceOf(SystemClock::class)
        ->and($clock->now()->getTimezone()->getName())->toBe('UTC')
        ->and(app(Clock::class))->toBe($clock);
});
