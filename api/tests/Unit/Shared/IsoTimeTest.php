<?php

declare(strict_types=1);

use App\Shared\Time\IsoTime;
use Carbon\CarbonImmutable;

it('writes a time in utc with a z suffix', function (): void {
    expect(IsoTime::format(CarbonImmutable::parse('2026-10-28 23:47:21', 'Europe/Paris')))->toBe('2026-10-28T22:47:21Z');
});

it('keeps a missing time missing', function (): void {
    expect(IsoTime::format(null))->toBeNull();
});
