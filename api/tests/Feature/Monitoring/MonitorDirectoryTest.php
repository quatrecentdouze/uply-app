<?php

declare(strict_types=1);

use App\Monitoring\Contracts\MonitorDirectory;
use App\Monitoring\Contracts\MonitorView;
use App\Monitoring\Models\Monitor;
use Illuminate\Support\Str;

it('gives other families a read only view of a monitor', function (): void {
    $monitor = Monitor::factory()->create(['name' => 'Billing API', 'url' => 'https://billing.example.com/health']);

    $view = app(MonitorDirectory::class)->find($monitor->id);

    expect($view)->toEqual(new MonitorView($monitor->id, $monitor->organization_id, 'Billing API', 'https://billing.example.com/health'));
});

it('finds nothing for an unknown monitor', function (): void {
    expect(app(MonitorDirectory::class)->find(Str::lower((string) Str::ulid())))->toBeNull();
});
