<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Monitoring\Models\Monitor;
use App\Monitoring\Settings\MonitoringSettings;
use App\Organizations\Models\Organization;
use Illuminate\Database\Seeder;

final class DemoSeeder extends Seeder
{
    public const string ORGANIZATION_SLUG = 'demo';

    public const string TARGET_URL = 'http://127.0.0.1:8090/health';

    public function run(MonitoringSettings $settings): void
    {
        $organization = Organization::query()->firstOrCreate(['slug' => self::ORGANIZATION_SLUG], ['name' => 'Demo']);

        Monitor::query()->updateOrCreate(
            ['organization_id' => $organization->id, 'url' => self::TARGET_URL],
            [
                'name' => 'Local demo target',
                'method' => $settings->monitorDefaults->method,
                'interval_seconds' => $settings->intervals->shortestSeconds(),
                'timeout_seconds' => $settings->probe->defaultTimeoutSeconds,
                'accepted_status_codes' => $settings->monitorDefaults->acceptedStatusCodes->toTexts(),
                'follow_redirects' => true,
                'is_paused' => false,
                'next_check_at' => now(),
            ],
        );
    }
}
