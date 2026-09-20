<?php

declare(strict_types=1);

namespace App\Monitoring\Http\Controllers;

use App\Monitoring\Actions\StatsReport;
use App\Monitoring\Http\Requests\StatsRequest;
use App\Monitoring\Http\Resources\OverviewResource;

final readonly class OverviewController
{
    public function __construct(private StatsReport $report) {}

    public function show(StatsRequest $request, string $organization): OverviewResource
    {
        return new OverviewResource($this->report->organization($organization, $request->window()));
    }
}
