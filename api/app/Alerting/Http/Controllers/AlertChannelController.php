<?php

declare(strict_types=1);

namespace App\Alerting\Http\Controllers;

use App\Alerting\Http\Resources\AlertChannelResource;
use App\Alerting\Storage\AlertChannelQuery;
use App\Shared\Http\PageRequest;
use App\Shared\Settings\PaginationSettings;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class AlertChannelController
{
    public function __construct(private AlertChannelQuery $channels) {}

    public function index(PageRequest $request, PaginationSettings $pagination, string $organization): AnonymousResourceCollection
    {
        return AlertChannelResource::collection($this->channels->page($organization, $request->perPage($pagination)));
    }

    public function show(string $organization, string $channel): AlertChannelResource
    {
        return new AlertChannelResource($this->channels->get($organization, $channel));
    }
}
