<?php

declare(strict_types=1);

it('gives what the status page editor needs', function (): void {
    $this->getJson('/api/v1/meta')
        ->assertOk()
        ->assertJsonPath('data.status_pages', [
            'limits' => ['max_pages_per_organization' => 10, 'max_monitors_per_page' => 50],
            'lengths' => [
                'title' => ['min' => 1, 'max' => 80],
                'description' => ['max' => 500],
                'display_name' => ['max' => 80],
                'slug' => ['min' => 3, 'max' => 48, 'pattern' => '^[a-z0-9-]+$'],
            ],
            'public' => ['bar_days' => 90, 'incident_days' => 14, 'cache_seconds' => 30],
            'overall_states' => ['operational', 'partial_outage', 'major_outage'],
        ]);
});
