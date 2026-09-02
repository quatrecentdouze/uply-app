<?php

declare(strict_types=1);

namespace Database\Factories\Monitoring\Models;

use App\Monitoring\Models\Monitor;
use App\Monitoring\Settings\MonitoringSettings;
use Database\Factories\Organizations\Models\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

final class MonitorFactory extends Factory
{
    protected $model = Monitor::class;

    public function definition(): array
    {
        $settings = app(MonitoringSettings::class);

        return [
            'organization_id' => OrganizationFactory::new(),
            'name' => fake()->words(2, true),
            'url' => 'https://'.fake()->unique()->domainWord().'.example.com/health',
            'method' => $settings->monitorDefaults->method,
            'interval_seconds' => $settings->intervals->defaultSeconds,
            'timeout_seconds' => $settings->probe->defaultTimeoutSeconds,
            'accepted_status_codes' => $settings->monitorDefaults->acceptedStatusCodes->toTexts(),
            'follow_redirects' => true,
            'is_paused' => false,
            'next_check_at' => now(),
        ];
    }

    public function paused(): self
    {
        return $this->state(fn (): array => ['is_paused' => true]);
    }
}
