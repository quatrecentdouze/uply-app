<?php

declare(strict_types=1);

use App\Monitoring\Models\Monitor;
use App\Monitoring\Settings\MonitoringSettings;
use App\Monitoring\Values\MonitorRequest;
use App\Monitoring\Values\RequestHeader;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Organization;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\Members;

it('stores the confirmation thresholds of the settings on a new monitor', function (): void {
    $organization = Organization::factory()->create();
    $defaults = app(MonitoringSettings::class)->confirmation->thresholds;

    $id = $this->actingAs(Members::seat($organization, Role::Member))
        ->postJson("/api/v1/organizations/{$organization->id}/monitors", ['name' => 'API', 'url' => 'https://api.example.com/health'])
        ->assertCreated()
        ->json('data.id');

    expect(Monitor::query()->findOrFail($id)->confirmationThresholds())->toEqual($defaults);
});

it('refuses a threshold under one in the database', function (string $column): void {
    $monitor = Monitor::factory()->create();

    expect(fn () => Monitor::query()->whereKey($monitor->id)->update([$column => 0]))->toThrow(QueryException::class);
})->with([
    'failure_threshold',
    'recovery_threshold',
]);

it('stores the five methods and refuses any other one in the database', function (): void {
    foreach (['get', 'head', 'post', 'put', 'patch'] as $method) {
        expect(Monitor::factory()->create(['method' => $method])->fresh()?->method->value)->toBe($method);
    }
    $monitor = Monitor::factory()->create();

    expect(fn () => Monitor::query()->whereKey($monitor->id)->update(['method' => 'delete']))->toThrow(QueryException::class);
});

it('keeps the request headers and body encrypted and out of the serialized monitor', function (): void {
    $monitor = Monitor::factory()->create();
    $monitor->setAttribute('request', (new MonitorRequest([new RequestHeader('Authorization', 'Bearer column-secret')], 'body-secret'))->toStored());
    $monitor->save();

    $raw = (string) DB::table('monitors')->where('id', $monitor->id)->value('request');
    $read = Monitor::query()->findOrFail($monitor->id);

    expect($raw)->not->toContain('column-secret')
        ->and($raw)->not->toContain('body-secret')
        ->and($read->checkRequest()->headerNames())->toBe(['Authorization'])
        ->and($read->checkRequest()->body)->toBe('body-secret')
        ->and($read->toArray())->not->toHaveKey('request')
        ->and((string) json_encode($read))->not->toContain('column-secret');
});

it('stores no request when there is nothing to send', function (): void {
    $monitor = Monitor::factory()->create();

    expect(DB::table('monitors')->where('id', $monitor->id)->value('request'))->toBeNull()
        ->and($monitor->checkRequest()->isEmpty())->toBeTrue();
});
