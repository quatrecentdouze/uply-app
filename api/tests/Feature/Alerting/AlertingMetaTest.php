<?php

declare(strict_types=1);

it('gives what the alert channel forms need', function (): void {
    $this->getJson('/api/v1/meta')
        ->assertOk()
        ->assertJsonPath('data.alerting', [
            'channel_types' => ['email', 'discord', 'slack'],
            'email_recipients' => ['min' => 1, 'max' => 10],
            'test_sends_per_minute' => 5,
            'max_channels_per_monitor' => 50,
            'lengths' => ['channel_name' => ['min' => 1, 'max' => 80], 'webhook_url' => ['max' => 2048]],
            'reminder_intervals_minutes' => [15, 30, 60, 120, 240, 720, 1440],
            'limits' => ['max_reminders_per_incident' => 24],
        ]);
});

it('lists the sections of every family', function (): void {
    expect(array_keys((array) $this->getJson('/api/v1/meta')->json('data')))->toBe(['alerting', 'api', 'maintenance', 'monitoring', 'organizations', 'realtime', 'status_pages']);
});
