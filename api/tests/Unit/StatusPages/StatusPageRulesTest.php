<?php

declare(strict_types=1);

use App\Monitoring\Contracts\MonitorStatus;
use App\StatusPages\Enums\OverallState;
use App\StatusPages\Rules\StatusPageRules;
use Carbon\CarbonImmutable;

it('says operational, partial or major outage from the shown monitors', function (array $statuses, OverallState $state): void {
    expect(StatusPageRules::overallState($statuses))->toBe($state);
})->with([
    'nothing shown' => [[], OverallState::Operational],
    'all up' => [[MonitorStatus::Up, MonitorStatus::Up], OverallState::Operational],
    'pending is not down' => [[MonitorStatus::Pending, MonitorStatus::Up], OverallState::Operational],
    'only paused' => [[MonitorStatus::Paused], OverallState::Operational],
    'one of two down' => [[MonitorStatus::Down, MonitorStatus::Up], OverallState::PartialOutage],
    'down and pending' => [[MonitorStatus::Down, MonitorStatus::Pending], OverallState::PartialOutage],
    'all down' => [[MonitorStatus::Down, MonitorStatus::Down], OverallState::MajorOutage],
    'all not paused down' => [[MonitorStatus::Down, MonitorStatus::Paused], OverallState::MajorOutage],
]);

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
