<?php

declare(strict_types=1);

namespace App\Monitoring\Http\Controllers;

use App\Maintenance\Contracts\MaintenanceSchedule;
use App\Monitoring\Actions\CreateMonitor;
use App\Monitoring\Actions\DeleteMonitor;
use App\Monitoring\Actions\UpdateMonitor;
use App\Monitoring\Http\Requests\MonitorListRequest;
use App\Monitoring\Http\Requests\StoreMonitorRequest;
use App\Monitoring\Http\Requests\UpdateMonitorRequest;
use App\Monitoring\Http\Resources\MonitorResource;
use App\Monitoring\Models\Monitor;
use App\Monitoring\Settings\MonitoringSettings;
use App\Monitoring\Storage\MonitorQuery;
use App\Monitoring\Storage\RecentUptimeQuery;
use App\Monitoring\Values\MaintenanceByMonitor;
use App\Shared\Clock;
use App\Shared\Settings\PaginationSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final readonly class MonitorController
{
    public function __construct(
        private MonitorQuery $monitors,
        private RecentUptimeQuery $uptimes,
        private MaintenanceSchedule $maintenance,
        private Clock $clock,
    ) {}

    public function index(MonitorListRequest $request, PaginationSettings $pagination, string $organization): AnonymousResourceCollection
    {
        $page = $this->monitors->page($organization, $request->status(), $request->search(), $request->perPage($pagination));
        $ids = MonitorQuery::idsOf($page);
        $uptimes = $this->uptimes->lastDay($ids);
        $maintenance = $this->maintenanceOf($ids);

        return MonitorResource::collection($page->through(
            fn (mixed $monitor): mixed => $monitor instanceof Monitor ? new MonitorResource($monitor, $uptimes, $maintenance) : $monitor,
        ));
    }

    public function store(StoreMonitorRequest $request, MonitoringSettings $settings, CreateMonitor $createMonitor, string $organization): JsonResponse
    {
        $monitor = $createMonitor($organization, $request->draft($settings));

        return $this->resource($monitor)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(string $organization, string $monitor): MonitorResource
    {
        return $this->resource($this->monitors->get($organization, $monitor));
    }

    public function update(UpdateMonitorRequest $request, UpdateMonitor $updateMonitor, string $organization, string $monitor): MonitorResource
    {
        $current = $this->monitors->get($organization, $monitor);

        return $this->resource($updateMonitor($current, $request->draftOver($current->draft()), $request->paused()));
    }

    public function destroy(DeleteMonitor $deleteMonitor, string $organization, string $monitor): Response
    {
        $deleteMonitor($this->monitors->get($organization, $monitor));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function resource(Monitor $monitor): MonitorResource
    {
        return new MonitorResource($monitor, $this->uptimes->lastDay([$monitor->id]), $this->maintenanceOf([$monitor->id]));
    }

    private function maintenanceOf(array $monitorIds): MaintenanceByMonitor
    {
        return new MaintenanceByMonitor($this->maintenance->windowsFor($monitorIds, $this->clock->now()));
    }
}
