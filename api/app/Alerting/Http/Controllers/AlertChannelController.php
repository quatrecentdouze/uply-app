<?php

declare(strict_types=1);

namespace App\Alerting\Http\Controllers;

use App\Alerting\Actions\ChannelChanges;
use App\Alerting\Http\Requests\StoreAlertChannelRequest;
use App\Alerting\Http\Requests\UpdateAlertChannelRequest;
use App\Alerting\Http\Resources\AlertChannelResource;
use App\Alerting\Settings\AlertingSettings;
use App\Alerting\Storage\AlertChannelQuery;
use App\Shared\Http\PageRequest;
use App\Shared\Settings\PaginationSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final readonly class AlertChannelController
{
    public function __construct(private AlertChannelQuery $channels) {}

    public function index(PageRequest $request, PaginationSettings $pagination, string $organization): AnonymousResourceCollection
    {
        return AlertChannelResource::collection($this->channels->page($organization, $request->perPage($pagination)));
    }

    public function store(StoreAlertChannelRequest $request, AlertingSettings $settings, ChannelChanges $changes, string $organization): JsonResponse
    {
        $channel = $changes->create($organization, $request->type(), $request->draft($settings));

        return (new AlertChannelResource($channel))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(string $organization, string $channel): AlertChannelResource
    {
        return new AlertChannelResource($this->channels->get($organization, $channel));
    }

    public function destroy(ChannelChanges $changes, string $organization, string $channel): Response
    {
        $changes->delete($this->channels->get($organization, $channel));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    public function update(UpdateAlertChannelRequest $request, AlertingSettings $settings, ChannelChanges $changes): AlertChannelResource
    {
        return new AlertChannelResource($changes->update($request->channel($this->channels), $request->draft($settings, $this->channels)));
    }
}
