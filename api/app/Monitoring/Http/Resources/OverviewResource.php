<?php

declare(strict_types=1);

namespace App\Monitoring\Http\Resources;

use App\Monitoring\Contracts\MonitorStatus;
use App\Monitoring\Values\OrganizationOverview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class OverviewResource extends JsonResource
{
    public function __construct(private readonly OrganizationOverview $overview)
    {
        parent::__construct($overview);
    }

    public function toArray(Request $request): array
    {
        $counts = $this->overview->counts;

        return [
            'window' => $this->overview->window->value,
            'counts' => [
                'up' => $counts->of(MonitorStatus::Up),
                'down' => $counts->of(MonitorStatus::Down),
                'pending' => $counts->of(MonitorStatus::Pending),
                'paused' => $counts->of(MonitorStatus::Paused),
            ],
            'uptime_percent' => $this->overview->summary->uptimePercent,
            'series' => SeriesPointResource::collection($this->overview->series),
        ];
    }
}
