<?php

declare(strict_types=1);

namespace App\StatusPages\Http\Resources;

use App\StatusPages\Values\PublicMonitor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PublicMonitorResource extends JsonResource
{
    public function __construct(private readonly PublicMonitor $monitor)
    {
        parent::__construct($monitor);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->monitor->id,
            'display_name' => $this->monitor->displayName,
            'state' => $this->monitor->state->value,
            'uptime_90d' => $this->monitor->uptime90d,
            'bars' => PublicBarResource::collection($this->monitor->bars),
        ];
    }
}
