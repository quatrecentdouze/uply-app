<?php

declare(strict_types=1);

namespace App\StatusPages\Http\Resources;

use App\StatusPages\Models\StatusPageMonitor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PageMonitorResource extends JsonResource
{
    public function __construct(private readonly StatusPageMonitor $monitor)
    {
        parent::__construct($monitor);
    }

    public function toArray(Request $request): array
    {
        return [
            'monitor_id' => $this->monitor->monitor_id,
            'display_name' => $this->monitor->display_name,
            'position' => $this->monitor->position,
        ];
    }
}
