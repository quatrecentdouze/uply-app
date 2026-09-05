<?php

declare(strict_types=1);

use App\Monitoring\Enums\MonitorState;
use App\Monitoring\Models\Monitor;
use App\Organizations\Models\Organization;
use Database\Seeders\DemoSeeder;

it('seeds one demo organization with a due monitor on the local target, twice without duplicates', function (): void {
    $this->seed(DemoSeeder::class);
    $this->seed(DemoSeeder::class);

    $monitor = Monitor::query()->sole();
    expect(Organization::query()->sole()->slug)->toBe(DemoSeeder::ORGANIZATION_SLUG)
        ->and($monitor->url)->toBe(DemoSeeder::TARGET_URL)
        ->and($monitor->interval_seconds)->toBe(30)
        ->and($monitor->state)->toBe(MonitorState::Pending)
        ->and($monitor->acceptedStatusCodes()->toTexts())->toBe(['200-299']);
});
