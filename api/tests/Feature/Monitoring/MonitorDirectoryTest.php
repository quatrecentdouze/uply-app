<?php

declare(strict_types=1);

use App\Monitoring\Contracts\MonitorDirectory;
use App\Monitoring\Contracts\MonitorStatus;
use App\Monitoring\Contracts\MonitorView;
use App\Monitoring\Enums\MonitorState;
use App\Monitoring\Models\Monitor;
use Illuminate\Support\Str;

it('gives other families a read only view of a monitor', function (): void {
    $monitor = Monitor::factory()->create(['name' => 'Billing API', 'url' => 'https://billing.example.com/health']);

    $view = app(MonitorDirectory::class)->find($monitor->id);

    expect($view)->toEqual(new MonitorView($monitor->id, $monitor->organization_id, 'Billing API', 'https://billing.example.com/health', MonitorStatus::Pending));
});

it('finds nothing for an unknown monitor', function (): void {
    expect(app(MonitorDirectory::class)->find(Str::lower((string) Str::ulid())))->toBeNull();
});

it('finds many monitors by id with their status, paused first', function (): void {
    $down = Monitor::factory()->create(['state' => MonitorState::Down]);
    $paused = Monitor::factory()->paused()->create(['state' => MonitorState::Down]);
    Monitor::factory()->create();

    $views = app(MonitorDirectory::class)->findMany([$down->id, $paused->id, Str::lower((string) Str::ulid())]);

    expect(array_keys($views))->toEqualCanonicalizing([$down->id, $paused->id])
        ->and($views[$down->id]->status)->toBe(MonitorStatus::Down)
        ->and($views[$paused->id]->status)->toBe(MonitorStatus::Paused)
        ->and(app(MonitorDirectory::class)->findMany([]))->toBe([]);
});
