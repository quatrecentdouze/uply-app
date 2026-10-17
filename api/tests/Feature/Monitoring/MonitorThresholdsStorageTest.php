<?php

declare(strict_types=1);

use App\Monitoring\Models\Monitor;
use App\Monitoring\Settings\MonitoringSettings;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Organization;
use Illuminate\Database\QueryException;
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
