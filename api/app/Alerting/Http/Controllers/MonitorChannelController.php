<?php

declare(strict_types=1);

namespace App\Alerting\Http\Controllers;

use App\Alerting\Http\Requests\LinkChannelsRequest;
use App\Alerting\Http\Resources\AlertChannelResource;
use App\Alerting\Storage\ChannelLinks;
use App\Monitoring\Contracts\MonitorDirectory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class MonitorChannelController
{
    public function __construct(
        private MonitorDirectory $monitors,
        private ChannelLinks $links,
    ) {}

    public function index(string $organization, string $monitor): AnonymousResourceCollection
    {
        return AlertChannelResource::collection($this->links->channelsOf($this->monitorIn($organization, $monitor)));
    }

    public function update(LinkChannelsRequest $request, string $organization, string $monitor): AnonymousResourceCollection
    {
        $monitorId = $this->monitorIn($organization, $monitor);
        $this->links->replace($monitorId, $request->channelIds());

        return AlertChannelResource::collection($this->links->channelsOf($monitorId));
    }

    private function monitorIn(string $organizationId, string $monitorId): string
    {
        $view = $this->monitors->find($monitorId);
        if ($view === null || $view->organizationId !== $organizationId) {
            throw new NotFoundHttpException;
        }

        return $view->id;
    }
}
