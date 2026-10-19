<?php

declare(strict_types=1);

use App\Monitoring\Events\MonitorChangedEvent;
use App\Monitoring\Models\Monitor;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Organization;
use Illuminate\Support\Facades\Event;
use Tests\Support\Members;

beforeEach(function (): void {
    $this->clock = freezeClock('2027-03-10 12:00:00');
    $this->organization = Organization::factory()->create();
    $this->actingAs(Members::seat($this->organization, Role::Member));
    $this->route = "/api/v1/organizations/{$this->organization->id}/monitors";
});

function keywordPayload(array $changes = []): array
{
    return [...['name' => 'Status page', 'url' => 'https://status.example.com/', 'keyword' => 'All systems go'], ...$changes];
}

it('takes a keyword that must be present by default', function (): void {
    $this->postJson($this->route, keywordPayload())
        ->assertCreated()
        ->assertJsonPath('data.keyword', 'All systems go')
        ->assertJsonPath('data.keyword_match', 'present');
});

it('takes a keyword that must be absent', function (): void {
    $this->postJson($this->route, keywordPayload(['keyword' => 'Maintenance', 'keyword_match' => 'absent']))
        ->assertCreated()
        ->assertJsonPath('data.keyword', 'Maintenance')
        ->assertJsonPath('data.keyword_match', 'absent');
});

it('answers no keyword for a plain monitor', function (): void {
    $this->postJson($this->route, ['name' => 'API', 'url' => 'https://api.example.com/health'])
        ->assertCreated()
        ->assertJsonPath('data.keyword', null)
        ->assertJsonPath('data.keyword_match', 'present');
});

it('validates the keyword', function (array $changes, string $field): void {
    $this->postJson($this->route, keywordPayload($changes))->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'too long' => [['keyword' => str_repeat('k', 201)], 'keyword'],
    'not a text' => [['keyword' => ['All']], 'keyword'],
    'unknown match' => [['keyword_match' => 'maybe'], 'keyword_match'],
    'with head' => [['method' => 'head'], 'keyword'],
]);

it('checks at once and clears the counters when the keyword or its match changes', function (array $changes): void {
    $id = (string) $this->postJson($this->route, keywordPayload())->assertCreated()->json('data.id');
    Monitor::query()->whereKey($id)->update(['consecutive_failures' => 1, 'next_check_at' => $this->clock->now()->addMinutes(3)]);
    Event::fake([MonitorChangedEvent::class]);

    $this->patchJson("{$this->route}/{$id}", $changes)->assertOk();

    $monitor = Monitor::query()->findOrFail($id);
    expect($monitor->consecutive_failures)->toBe(0)
        ->and($monitor->nextCheckAt()->toIso8601ZuluString())->toBe('2027-03-10T12:00:00Z');
    Event::assertDispatchedOnce(MonitorChangedEvent::class);
})->with([
    'keyword' => [['keyword' => 'Operational']],
    'match' => [['keyword_match' => 'absent']],
    'cleared' => [['keyword' => null]],
]);

it('keeps the keyword on a patch without it and clears it with null', function (): void {
    $id = (string) $this->postJson($this->route, keywordPayload())->assertCreated()->json('data.id');

    $this->patchJson("{$this->route}/{$id}", ['name' => 'Renamed'])->assertOk()->assertJsonPath('data.keyword', 'All systems go');
    $this->patchJson("{$this->route}/{$id}", ['keyword' => null])->assertOk()->assertJsonPath('data.keyword', null);
});

it('refuses head while a keyword stays, unless the keyword is cleared with it', function (): void {
    $id = (string) $this->postJson($this->route, keywordPayload())->assertCreated()->json('data.id');

    $this->patchJson("{$this->route}/{$id}", ['method' => 'head'])->assertUnprocessable()->assertJsonValidationErrors('method');
    $this->patchJson("{$this->route}/{$id}", ['method' => 'head', 'keyword' => null])->assertOk()->assertJsonPath('data.method', 'head');
});
