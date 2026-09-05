<?php

declare(strict_types=1);

use App\Incidents\Rules\IncidentRules;
use Carbon\CarbonImmutable;

it('measures an incident from its first failure to its first success', function (): void {
    $startedAt = CarbonImmutable::parse('2026-10-28 12:01:20', 'UTC');
    $resolvedAt = IncidentRules::resolutionTime($startedAt, CarbonImmutable::parse('2026-10-28 12:02:40', 'UTC'));

    expect($resolvedAt->toTimeString())->toBe('12:02:40')
        ->and(IncidentRules::durationSeconds($startedAt, $resolvedAt))->toBe(80);
});

it('never resolves an incident before it started', function (): void {
    $startedAt = CarbonImmutable::parse('2026-10-28 12:01:20', 'UTC');
    $resolvedAt = IncidentRules::resolutionTime($startedAt, CarbonImmutable::parse('2026-10-28 12:00:00', 'UTC'));

    expect($resolvedAt->equalTo($startedAt))->toBeTrue()
        ->and(IncidentRules::durationSeconds($startedAt, $resolvedAt))->toBe(0);
});
