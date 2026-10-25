<?php

declare(strict_types=1);

namespace App\Maintenance\Http\Controllers;

use App\Maintenance\Http\Requests\MaintenanceWindowListRequest;
use App\Maintenance\Http\Resources\MaintenanceWindowResource;
use App\Maintenance\Models\MaintenanceWindow;
use App\Maintenance\Storage\MaintenanceWindowQuery;
use App\Shared\Clock;
use App\Shared\Settings\PaginationSettings;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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

    public function show(string $organization, string $maintenanceWindow): MaintenanceWindowResource
    {
        return new MaintenanceWindowResource($this->windows->get($organization, $maintenanceWindow), $this->clock->now());
    }
}
