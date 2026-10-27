<?php

declare(strict_types=1);

use App\Alerting\Rules\ReminderRules;
use App\Alerting\Values\ReminderHistory;
use Carbon\CarbonImmutable;

function reminderMoment(string $time): CarbonImmutable
{
    return CarbonImmutable::parse("2027-03-10 {$time}", 'UTC');
}

function nextReminderAt(string $now, ?string $lastAlertAt, int $sent = 0, bool $silenced = false, int $max = 24): ?int
{
    return ReminderRules::nextNumber(
        new ReminderHistory($lastAlertAt === null ? null : reminderMoment($lastAlertAt), $sent),
        reminderMoment('12:00:00'),
        reminderMoment($now),
        intervalMinutes: 15,
        maxReminders: $max,
        silenced: $silenced,
    );
}

it('sends the first reminder one interval after the down alert', function (): void {
    expect(nextReminderAt('12:16:00', '12:01:30'))->toBeNull()
        ->and(nextReminderAt('12:16:30', '12:01:30'))->toBe(1);
});

it('counts from the opening of the incident when the channel got no alert yet', function (): void {
    expect(nextReminderAt('12:14:59', null))->toBeNull()
        ->and(nextReminderAt('12:15:00', null))->toBe(1);
});

it('counts the next interval from the latest reminder and numbers it', function (): void {
    expect(nextReminderAt('12:44:00', '12:30:00', sent: 2))->toBeNull()
        ->and(nextReminderAt('12:45:00', '12:30:00', sent: 2))->toBe(3);
});

it('sends one reminder only after a long silence', function (): void {
    expect(nextReminderAt('18:00:00', '12:30:00', sent: 2))->toBe(3);
});

it('stops at the most reminders of an incident', function (): void {
    expect(nextReminderAt('20:00:00', '12:30:00', sent: 24))->toBeNull()
        ->and(nextReminderAt('20:00:00', '12:30:00', sent: 2, max: 2))->toBeNull()
        ->and(nextReminderAt('20:00:00', '12:30:00', sent: 1, max: 2))->toBe(2);
});

it('sends nothing while the monitor is silenced by a window or flapping', function (): void {
    expect(nextReminderAt('20:00:00', '12:30:00', silenced: true))->toBeNull();
});

it('refuses a negative count of reminders', function (): void {
    expect(fn () => new ReminderHistory(null, -1))->toThrow(InvalidArgumentException::class)
        ->and(ReminderHistory::none())->toEqual(new ReminderHistory(null, 0));
});
