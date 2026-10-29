<?php

declare(strict_types=1);

use App\Monitoring\Events\MonitorFlappingEndedEvent;
use App\Monitoring\Models\Monitor;
use App\Monitoring\Values\FlappingState;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Tests\Support\Members;

beforeEach(function (): void {
    freezeClock('2027-03-10 12:10:00');
    Event::fake([MonitorFlappingEndedEvent::class]);
    $this->organization = Organization::factory()->create();
    $this->monitor = Monitor::factory()->create(['organization_id' => $this->organization->id, 'url' => 'https://api.example.com/health']);
    $changes = array_map(fn (string $time): CarbonImmutable => CarbonImmutable::parse("2027-03-10 {$time}", 'UTC'), ['12:00:00', '12:01:00', '12:02:00', '12:03:00', '12:04:00']);
    $this->monitor->applyFlapping(new FlappingState(CarbonImmutable::parse('2027-03-10 12:04:00', 'UTC'), $changes));
    $this->monitor->save();
    $this->actingAs(Members::seat($this->organization, Role::Member));
    $this->monitorUrl = "/api/v1/organizations/{$this->organization->id}/monitors/{$this->monitor->id}";
});

it('forgets the flapping and its history without an alert when the check changes', function (array $change): void {
    $this->patchJson($this->monitorUrl, $change)->assertOk();

    expect($this->monitor->fresh()?->flappingState())->toEqual(FlappingState::none());
    Event::assertNotDispatched(MonitorFlappingEndedEvent::class);
})->with([
    'url' => [['url' => 'https://api.example.com/ready']],
    'method' => [['method' => 'head']],
    'accepted codes' => [['accepted_status_codes' => ['200-399']]],
    'redirects' => [['follow_redirects' => false]],
    'request headers' => [['request_headers' => [['name' => 'X-Check', 'value' => 'uply']]]],
]);

it('keeps the flapping on a rename, a new interval or a pause', function (array $change): void {
    $this->patchJson($this->monitorUrl, $change)->assertOk();

    expect($this->monitor->fresh()?->flapping_since?->toTimeString())->toBe('12:04:00')
        ->and($this->monitor->fresh()?->flappingState()->changeCount())->toBe(5);
})->with([
    'name' => [['name' => 'Renamed']],
    'interval' => [['interval_seconds' => 300]],
    'pause' => [['is_paused' => true]],
]);
