<?php

declare(strict_types=1);

use App\Monitoring\Contracts\MonitorStatus;
use App\StatusPages\Enums\OverallState;
use App\StatusPages\Enums\PublicMonitorState;
use App\StatusPages\Rules\StatusPageRules;
use Carbon\CarbonImmutable;

it('says operational, partial or major outage from the shown monitors', function (array $states, OverallState $state): void {
    expect(StatusPageRules::overallState($states))->toBe($state);
})->with([
    'nothing shown' => [[], OverallState::Operational],
    'all up' => [[PublicMonitorState::Up, PublicMonitorState::Up], OverallState::Operational],
    'pending is not down' => [[PublicMonitorState::Pending, PublicMonitorState::Up], OverallState::Operational],
    'only paused' => [[PublicMonitorState::Paused], OverallState::Operational],
    'only in maintenance' => [[PublicMonitorState::Maintenance], OverallState::Operational],
    'one of two down' => [[PublicMonitorState::Down, PublicMonitorState::Up], OverallState::PartialOutage],
    'down and pending' => [[PublicMonitorState::Down, PublicMonitorState::Pending], OverallState::PartialOutage],
    'all down' => [[PublicMonitorState::Down, PublicMonitorState::Down], OverallState::MajorOutage],
    'all not paused down' => [[PublicMonitorState::Down, PublicMonitorState::Paused], OverallState::MajorOutage],
    'all out of maintenance down' => [[PublicMonitorState::Down, PublicMonitorState::Maintenance], OverallState::MajorOutage],
]);

it('shows a monitor in an active window as in maintenance whatever its state', function (MonitorStatus $status): void {
    expect(PublicMonitorState::of($status, inMaintenance: true))->toBe(PublicMonitorState::Maintenance)
        ->and(PublicMonitorState::of($status, inMaintenance: false)->value)->toBe($status->value);
})->with(MonitorStatus::cases());

it('names a shown monitor by its display name or else its monitor name, cut to the limit', function (?string $given, string $expected): void {
    expect(StatusPageRules::displayName($given, '  Checkout API  ', 8))->toBe($expected);
})->with([
    'given' => ['Shop', 'Shop'],
    'given with spaces' => ['  Shop  ', 'Shop'],
    'blank' => ['   ', 'Checkout'],
    'none' => [null, 'Checkout'],
]);

it('starts the incident history the given days before now', function (): void {
    expect(StatusPageRules::incidentsSince(CarbonImmutable::parse('2027-03-10 12:00:00', 'UTC'), 14)->toIso8601ZuluString())->toBe('2027-02-24T12:00:00Z');
});
