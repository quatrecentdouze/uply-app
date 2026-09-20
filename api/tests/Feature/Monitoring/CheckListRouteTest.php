<?php

declare(strict_types=1);

use App\Monitoring\Models\Monitor;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Organization;
use Tests\Support\CheckHistory;
use Tests\Support\Members;

function checksUrl(Monitor $monitor, string $query = ''): string
{
    return "/api/v1/organizations/{$monitor->organization_id}/monitors/{$monitor->id}/checks{$query}";
}

beforeEach(function (): void {
    $this->clock = freezeClock('2027-03-10 12:00:00');
    CheckHistory::keepDaysBefore($this->clock->now(), 31);
    $this->organization = Organization::factory()->create();
    $this->monitor = Monitor::factory()->state(['organization_id' => $this->organization->id])->create();
});

it('lists the raw results newest first to every role', function (Role $role): void {
    $now = $this->clock->now();
    CheckHistory::result($this->monitor->id, $now->subMinutes(2), up: true, responseMs: 120, timings: ['dns_ms' => 3, 'connect_ms' => 10, 'tls_ms' => 25, 'ttfb_ms' => 90]);
    CheckHistory::result($this->monitor->id, $now->subMinute(), up: false, responseMs: null);
    CheckHistory::result(Monitor::factory()->create()->id, $now->subMinute(), up: true);

    $this->actingAs(Members::seat($this->organization, $role))->getJson(checksUrl($this->monitor))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.checked_at', '2027-03-10T11:59:00Z')
        ->assertJsonPath('data.0.outcome', 'down')
        ->assertJsonPath('data.0.failure_kind', 'timeout')
        ->assertJsonPath('data.0.http_status', null)
        ->assertJsonPath('data.0.response_ms', null)
        ->assertJsonPath('data.0.error_message', 'HTTP 500')
        ->assertJsonPath('data.1.checked_at', '2027-03-10T11:58:00Z')
        ->assertJsonPath('data.1.outcome', 'up')
        ->assertJsonPath('data.1.http_status', 200)
        ->assertJsonPath('data.1.response_ms', 120)
        ->assertJsonPath('data.1.dns_ms', 3)
        ->assertJsonPath('data.1.connect_ms', 10)
        ->assertJsonPath('data.1.tls_ms', 25)
        ->assertJsonPath('data.1.ttfb_ms', 90)
        ->assertJsonMissingPath('data.0.id')
        ->assertJsonPath('meta.next_cursor', null);
})->with([Role::Owner, Role::Admin, Role::Member, Role::Viewer]);

it('walks the results with the cursor without skipping a check of the same second', function (): void {
    $now = $this->clock->now();
    foreach ([5, 4, 4, 3, 2] as $minutesAgo) {
        CheckHistory::result($this->monitor->id, $now->subMinutes($minutesAgo), up: true, responseMs: 100 + $minutesAgo);
    }
    $this->actingAs(Members::seat($this->organization, Role::Viewer));

    $seen = [];
    $cursor = null;
    do {
        $page = $this->getJson(checksUrl($this->monitor, '?per_page=2'.($cursor === null ? '' : '&cursor='.urlencode($cursor))))->assertOk();
        $seen = [...$seen, ...array_column($page->json('data'), 'checked_at')];
        $cursor = $page->json('meta.next_cursor');
    } while (is_string($cursor));

    expect($seen)->toBe([
        '2027-03-10T11:58:00Z',
        '2027-03-10T11:57:00Z',
        '2027-03-10T11:56:00Z',
        '2027-03-10T11:56:00Z',
        '2027-03-10T11:55:00Z',
    ]);
});

it('keeps the results between from and to', function (): void {
    $now = $this->clock->now();
    foreach ([30, 20, 10] as $minutesAgo) {
        CheckHistory::result($this->monitor->id, $now->subMinutes($minutesAgo), up: true);
    }

    $response = $this->actingAs(Members::seat($this->organization, Role::Viewer))
        ->getJson(checksUrl($this->monitor, '?from='.urlencode('2027-03-10T11:35:00Z').'&to='.urlencode('2027-03-10T11:45:00Z')))
        ->assertOk();

    expect(array_column($response->json('data'), 'checked_at'))->toBe(['2027-03-10T11:40:00Z']);
});

it('refuses a window outside the raw retention', function (string $query, string $field): void {
    $this->actingAs(Members::seat($this->organization, Role::Viewer))
        ->getJson(checksUrl($this->monitor, $query))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'from before the retention' => ['?from='.urlencode('2027-02-07T23:59:59Z'), 'from'],
    'to before from' => ['?from='.urlencode('2027-03-10T11:00:00Z').'&to='.urlencode('2027-03-10T10:00:00Z'), 'to'],
    'from as text' => ['?from=yesterday-ish', 'from'],
    'page too big' => ['?per_page=500', 'per_page'],
]);

it('hides the checks from a non member and of a monitor of another organization', function (): void {
    $foreign = Monitor::factory()->create();

    $this->actingAs(Members::outsider())->getJson(checksUrl($this->monitor))->assertNotFound();
    $this->actingAs(Members::seat($this->organization, Role::Owner))
        ->getJson("/api/v1/organizations/{$this->organization->id}/monitors/{$foreign->id}/checks")
        ->assertNotFound();
});
