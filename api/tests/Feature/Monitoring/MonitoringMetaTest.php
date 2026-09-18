<?php

declare(strict_types=1);

it('gives what the monitor forms need', function (): void {
    $this->getJson('/api/v1/meta')
        ->assertOk()
        ->assertJsonPath('data.monitoring', [
            'intervals' => ['allowed_seconds' => [30, 60, 120, 300, 600, 900, 1800, 3600], 'default_seconds' => 60],
            'timeouts' => ['min_seconds' => 1, 'max_seconds' => 30, 'default_seconds' => 10],
            'methods' => ['get', 'head'],
            'default_method' => 'get',
            'default_accepted_status_codes' => ['200-299'],
            'windows' => ['24h', '7d', '30d'],
            'limits' => ['max_monitors_per_organization' => 100, 'max_accepted_status_ranges' => 20],
            'lengths' => ['monitor_name' => ['min' => 1, 'max' => 100], 'url' => ['max' => 2048]],
        ]);
});
