<?php

declare(strict_types=1);

namespace Database\Factories\Incidents\Models;

use App\Incidents\Models\Incident;
use App\Monitoring\Models\Monitor;
use Database\Factories\Monitoring\Models\MonitorFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

final class IncidentFactory extends Factory
{
    protected $model = Incident::class;

    public function definition(): array
    {
        return [
            'monitor_id' => MonitorFactory::new(),
            'organization_id' => fn (array $attributes): mixed => Monitor::query()->whereKey($attributes['monitor_id'])->value('organization_id'),
            'started_at' => now()->subMinutes(5),
            'cause_failure_kind' => 'http_status',
            'cause_http_status' => 500,
        ];
    }
}
