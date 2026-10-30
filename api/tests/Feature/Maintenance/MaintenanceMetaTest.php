<?php

declare(strict_types=1);

it('gives what the maintenance screens need', function (): void {
    $this->getJson('/api/v1/meta')
        ->assertOk()
        ->assertJsonPath('data.maintenance', [
            'lengths' => ['name' => ['min' => 1, 'max' => 100]],
            'limits' => ['max_open_windows' => 50, 'min_monitors' => 1, 'max_monitors' => 100],
            'max_duration_hours' => 168,
            'max_days_ahead' => 90,
            'start_grace_seconds' => 60,
            'states' => ['upcoming', 'active', 'ended'],
        ]);
});
