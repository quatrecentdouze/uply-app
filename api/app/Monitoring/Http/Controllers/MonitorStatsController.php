<?php

declare(strict_types=1);

namespace App\Monitoring\Http\Controllers;

use App\Monitoring\Actions\StatsReport;
use App\Monitoring\Http\Requests\StatsRequest;
use App\Monitoring\Http\Resources\MonitorStatsResource;
use App\Monitoring\Storage\MonitorQuery;

final readonly class MonitorStatsController
{
    public function __construct(
        private MonitorQuery $monitors,
        private StatsReport $report,
    ) {}

    public function show(StatsRequest $request, string $organization, string $monitor): MonitorStatsResource
    {
        $found = $this->monitors->get($organization, $monitor);

        return new MonitorStatsResource($this->report->monitor($found->id, $request->window()));
    }
}
