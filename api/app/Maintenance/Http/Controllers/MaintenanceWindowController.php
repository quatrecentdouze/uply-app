<?php

declare(strict_types=1);

namespace App\Maintenance\Http\Controllers;

use App\Maintenance\Actions\CreateMaintenanceWindow;
use App\Maintenance\Actions\EndMaintenanceWindow;
use App\Maintenance\Actions\UpdateMaintenanceWindow;
use App\Maintenance\Http\Requests\MaintenanceWindowListRequest;
use App\Maintenance\Http\Requests\StoreMaintenanceWindowRequest;
use App\Maintenance\Http\Requests\UpdateMaintenanceWindowRequest;
use App\Maintenance\Http\Resources\MaintenanceWindowResource;
use App\Maintenance\Models\MaintenanceWindow;
use App\Maintenance\Settings\MaintenanceSettings;
use App\Maintenance\Storage\MaintenanceWindowQuery;
use App\Monitoring\Contracts\MonitorDirectory;
use App\Shared\Clock;
use App\Shared\Settings\PaginationSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final readonly class MaintenanceWindowController
{
    public function __construct(
        private MaintenanceWindowQuery $windows,
        private Clock $clock,
    ) {}

    public function index(MaintenanceWindowListRequest $request, PaginationSettings $pagination, string $organization): AnonymousResourceCollection
    {
        $now = $this->clock->now();
        $page = $this->windows->page($organization, $request->state(), $now, $request->perPage($pagination));

        return MaintenanceWindowResource::collection($page->through(
            fn (mixed $window): mixed => $window instanceof MaintenanceWindow ? new MaintenanceWindowResource($window, $now) : $window,
        ));
    }

    public function store(StoreMaintenanceWindowRequest $request, MaintenanceSettings $settings, MonitorDirectory $monitors, CreateMaintenanceWindow $createWindow, string $organization): JsonResponse
    {
        $window = $createWindow($organization, $request->draft($settings, $monitors), $request->authorId());

        return (new MaintenanceWindowResource($window, $this->clock->now()))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(string $organization, string $maintenanceWindow): MaintenanceWindowResource
    {
        return new MaintenanceWindowResource($this->windows->get($organization, $maintenanceWindow), $this->clock->now());
    }

    public function update(UpdateMaintenanceWindowRequest $request, MaintenanceSettings $settings, MonitorDirectory $monitors, UpdateMaintenanceWindow $updateWindow, string $organization, string $maintenanceWindow): MaintenanceWindowResource
    {
        $window = $updateWindow($this->windows->get($organization, $maintenanceWindow), $request->draft($settings, $monitors));

        return new MaintenanceWindowResource($window, $this->clock->now());
    }

    public function end(EndMaintenanceWindow $endWindow, string $organization, string $maintenanceWindow): MaintenanceWindowResource
    {
        $window = $endWindow($this->windows->get($organization, $maintenanceWindow));

        return new MaintenanceWindowResource($window, $this->clock->now());
    }
}
