<?php

declare(strict_types=1);

namespace App\Monitoring\Http\Controllers;

use App\Monitoring\Http\Requests\MonitorListRequest;
use App\Monitoring\Http\Resources\MonitorResource;
use App\Monitoring\Models\Monitor;
use App\Monitoring\Storage\MonitorQuery;
use App\Monitoring\Storage\RecentUptimeQuery;
use App\Shared\Settings\PaginationSettings;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class MonitorController
{
    public function __construct(
        private MonitorQuery $monitors,
        private RecentUptimeQuery $uptimes,
    ) {}

    public function index(MonitorListRequest $request, PaginationSettings $pagination, string $organization): AnonymousResourceCollection
    {
        $page = $this->monitors->page($organization, $request->status(), $request->search(), $request->perPage($pagination));
        $uptimes = $this->uptimes->lastDay(MonitorQuery::idsOf($page));

        return MonitorResource::collection($page->through(
            fn (mixed $monitor): mixed => $monitor instanceof Monitor ? new MonitorResource($monitor, $uptimes) : $monitor,
        ));
    }

    public function show(string $organization, string $monitor): MonitorResource
    {
        return $this->resource($this->monitors->get($organization, $monitor));
    }

    private function resource(Monitor $monitor): MonitorResource
    {
        return new MonitorResource($monitor, $this->uptimes->lastDay([$monitor->id]));
    }
}
