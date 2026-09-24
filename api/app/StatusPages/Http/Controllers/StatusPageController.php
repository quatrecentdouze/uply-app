<?php

declare(strict_types=1);

namespace App\StatusPages\Http\Controllers;

use App\Monitoring\Contracts\MonitorDirectory;
use App\Shared\Http\PageRequest;
use App\Shared\Settings\PaginationSettings;
use App\StatusPages\Actions\CreateStatusPage;
use App\StatusPages\Http\Requests\StoreStatusPageRequest;
use App\StatusPages\Http\Resources\StatusPageResource;
use App\StatusPages\Settings\StatusPagesSettings;
use App\StatusPages\Storage\StatusPageQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final readonly class StatusPageController
{
    public function __construct(
        private StatusPageQuery $pages,
        private StatusPagesSettings $settings,
        private MonitorDirectory $monitors,
    ) {}

    public function index(PageRequest $request, PaginationSettings $pagination, string $organization): AnonymousResourceCollection
    {
        return StatusPageResource::collection($this->pages->page($organization, $request->perPage($pagination)));
    }

    public function store(StoreStatusPageRequest $request, CreateStatusPage $createStatusPage, string $organization): JsonResponse
    {
        $page = $createStatusPage($organization, $request->draft($this->settings, $this->monitors));

        return (new StatusPageResource($page->load('monitors')))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(string $organization, string $statusPage): StatusPageResource
    {
        return new StatusPageResource($this->pages->get($organization, $statusPage));
    }
}
