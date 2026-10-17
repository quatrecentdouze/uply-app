<?php

declare(strict_types=1);

use App\Monitoring\Enums\MonitorChange;
use App\Monitoring\Events\MonitorChangedEvent;
use App\Monitoring\Models\Monitor;
use App\Monitoring\Settings\MonitoringSettings;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Organization;
use Illuminate\Support\Facades\Event;
use Tests\Support\Members;

function createMonitorUrl(Organization $organization): string
{
    return "/api/v1/organizations/{$organization->id}/monitors";
}

beforeEach(function (): void {
    $this->clock = freezeClock('2027-03-10 12:00:00');
    $this->organization = Organization::factory()->create();
});

it('creates a pending monitor with the defaults for the roles that manage monitors', function (Role $role): void {
    Event::fake([MonitorChangedEvent::class]);

    $response = $this->actingAs(Members::seat($this->organization, $role))
        ->postJson(createMonitorUrl($this->organization), ['name' => 'API', 'url' => 'https://api.example.com/health'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'API')
        ->assertJsonPath('data.url', 'https://api.example.com/health')
        ->assertJsonPath('data.method', 'get')
        ->assertJsonPath('data.interval_seconds', 60)
        ->assertJsonPath('data.timeout_seconds', 10)
        ->assertJsonPath('data.accepted_status_codes', ['200-299'])
        ->assertJsonPath('data.follow_redirects', true)
        ->assertJsonPath('data.failure_threshold', 2)
        ->assertJsonPath('data.recovery_threshold', 2)
        ->assertJsonPath('data.is_paused', false)
        ->assertJsonPath('data.state', 'pending')
        ->assertJsonPath('data.last_checked_at', null)
        ->assertJsonPath('data.uptime_24h', null);

    $monitor = Monitor::query()->sole();
    expect($response->json('data.id'))->toBe($monitor->id)
        ->and($monitor->organization_id)->toBe($this->organization->id)
        ->and($monitor->next_check_at?->toIso8601ZuluString())->toBe('2027-03-10T12:00:00Z');
    Event::assertDispatched(MonitorChangedEvent::class, fn (MonitorChangedEvent $event): bool => $event->monitorId === $monitor->id
        && $event->organizationId === $this->organization->id
        && $event->change === MonitorChange::Created);
})->with([Role::Owner, Role::Admin, Role::Member]);

it('takes every field of the form', function (): void {
    $this->actingAs(Members::seat($this->organization, Role::Member))
        ->postJson(createMonitorUrl($this->organization), [
            'name' => 'Ready',
            'url' => 'http://ready.example.com/',
            'method' => 'head',
            'interval_seconds' => 300,
            'timeout_seconds' => 30,
            'accepted_status_codes' => ['200-299', '304'],
            'follow_redirects' => false,
            'failure_threshold' => 1,
            'recovery_threshold' => 10,
        ])
        ->assertCreated()
        ->assertJsonPath('data.method', 'head')
        ->assertJsonPath('data.interval_seconds', 300)
        ->assertJsonPath('data.timeout_seconds', 30)
        ->assertJsonPath('data.accepted_status_codes', ['200-299', '304'])
        ->assertJsonPath('data.follow_redirects', false)
        ->assertJsonPath('data.failure_threshold', 1)
        ->assertJsonPath('data.recovery_threshold', 10);
});

it('takes the methods that send a body', function (string $method): void {
    $this->actingAs(Members::seat($this->organization, Role::Member))
        ->postJson(createMonitorUrl($this->organization), ['name' => 'Hook', 'url' => 'https://hooks.example.com/ping', 'method' => $method])
        ->assertCreated()
        ->assertJsonPath('data.method', $method);
})->with(['post', 'put', 'patch']);

it('refuses a viewer and hides the organization from a non member', function (): void {
    $payload = ['name' => 'API', 'url' => 'https://api.example.com/health'];

    $this->actingAs(Members::seat($this->organization, Role::Viewer))->postJson(createMonitorUrl($this->organization), $payload)->assertForbidden();
    $this->actingAs(Members::outsider())->postJson(createMonitorUrl($this->organization), $payload)->assertNotFound();

    expect(Monitor::query()->count())->toBe(0);
});

it('validates the form', function (array $payload, string $field): void {
    $this->actingAs(Members::seat($this->organization, Role::Admin))
        ->postJson(createMonitorUrl($this->organization), [...['name' => 'API', 'url' => 'https://api.example.com/health'], ...$payload])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'no name' => [['name' => ''], 'name'],
    'long name' => [['name' => str_repeat('n', 101)], 'name'],
    'no url' => [['url' => ''], 'url'],
    'long url' => [['url' => 'https://example.com/'.str_repeat('a', 2030)], 'url'],
    'ftp url' => [['url' => 'ftp://example.com/'], 'url'],
    'url with credentials' => [['url' => 'https://user:secret@example.com/'], 'url'],
    'metadata address' => [['url' => 'http://169.254.169.254/latest'], 'url'],
    'private address' => [['url' => 'http://10.0.0.8/'], 'url'],
    'delete method' => [['method' => 'delete'], 'method'],
    'interval off the list' => [['interval_seconds' => 45], 'interval_seconds'],
    'timeout too long' => [['timeout_seconds' => 31], 'timeout_seconds'],
    'timeout zero' => [['timeout_seconds' => 0], 'timeout_seconds'],
    'no accepted code' => [['accepted_status_codes' => []], 'accepted_status_codes'],
    'accepted code as text' => [['accepted_status_codes' => ['ok']], 'accepted_status_codes.0'],
    'accepted code out of range' => [['accepted_status_codes' => ['200-700']], 'accepted_status_codes.0'],
    'too many accepted ranges' => [['accepted_status_codes' => array_fill(0, 21, '200')], 'accepted_status_codes'],
    'redirects as text' => [['follow_redirects' => 'often'], 'follow_redirects'],
    'failure threshold zero' => [['failure_threshold' => 0], 'failure_threshold'],
    'failure threshold eleven' => [['failure_threshold' => 11], 'failure_threshold'],
    'recovery threshold as text' => [['recovery_threshold' => 'two'], 'recovery_threshold'],
    'recovery threshold eleven' => [['recovery_threshold' => 11], 'recovery_threshold'],
]);

it('explains a blocked url with its own text', function (): void {
    $this->actingAs(Members::seat($this->organization, Role::Owner))
        ->postJson(createMonitorUrl($this->organization), ['name' => 'Metadata', 'url' => 'http://169.254.169.254/'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.url.0', 'The URL points to an address Uply never checks.');
});

it('accepts a private address where private targets are allowed', function (): void {
    config(['monitoring.probe.allow_private_targets' => true]);
    app()->forgetInstance(MonitoringSettings::class);

    $this->actingAs(Members::seat($this->organization, Role::Owner))
        ->postJson(createMonitorUrl($this->organization), ['name' => 'Local', 'url' => 'http://127.0.0.1:8090/health'])
        ->assertCreated();
});

it('stops at the monitor limit of the organization', function (): void {
    config(['monitoring.limits.max_monitors_per_organization' => 2]);
    app()->forgetInstance(MonitoringSettings::class);
    Monitor::factory()->count(2)->state(['organization_id' => $this->organization->id])->create();
    Monitor::factory()->count(2)->create();

    $this->actingAs(Members::seat($this->organization, Role::Owner))
        ->postJson(createMonitorUrl($this->organization), ['name' => 'Third', 'url' => 'https://third.example.com/'])
        ->assertConflict()
        ->assertJsonPath('code', 'monitor_limit_reached')
        ->assertJsonPath('message', 'This organization already has the most monitors allowed (2).');

    expect(Monitor::query()->where('organization_id', $this->organization->id)->count())->toBe(2);
});
