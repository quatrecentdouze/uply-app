<?php

declare(strict_types=1);

namespace App\Incidents\Http\Controllers;

use App\Incidents\Http\Requests\IncidentListRequest;
use App\Incidents\Http\Resources\IncidentResource;
use App\Incidents\Models\Incident;
use App\Incidents\Storage\IncidentQuery;
use App\Shared\Settings\PaginationSettings;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class IncidentController
{
    public function __construct(private IncidentQuery $incidents) {}

    public function index(IncidentListRequest $request, PaginationSettings $pagination, string $organization): AnonymousResourceCollection
    {
        $page = $this->incidents->page($organization, $request->state(), $request->monitorId(), $request->perPage($pagination));
        $names = $this->incidents->monitorNames($page->items());

        return IncidentResource::collection($page->through(
            fn (mixed $incident): mixed => $incident instanceof Incident ? new IncidentResource($incident, $names) : $incident,
        ));
    }
}
