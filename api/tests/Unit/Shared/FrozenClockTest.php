<?php

declare(strict_types=1);

use Tests\Support\FrozenClock;

it('stays still until moved', function (): void {
    $clock = FrozenClock::at('2026-10-28 12:00:00');

    expect($clock->now()->toIso8601String())->toBe('2026-10-28T12:00:00+00:00');

    $clock->advance(20);

    expect($clock->now()->toIso8601String())->toBe('2026-10-28T12:00:20+00:00');
});
