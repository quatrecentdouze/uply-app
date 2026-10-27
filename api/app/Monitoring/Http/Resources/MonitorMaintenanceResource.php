<?php

declare(strict_types=1);

namespace App\Monitoring\Http\Resources;

use App\Maintenance\Contracts\ScheduledWindow;
use App\Shared\Time\IsoTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class MonitorMaintenanceResource extends JsonResource
{
    public function __construct(private readonly ScheduledWindow $window)
    {
        parent::__construct($window);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->window->id,
            'name' => $this->window->name,
            'starts_at' => IsoTime::of($this->window->startsAt),
            'ends_at' => IsoTime::of($this->window->endsAt),
        ];
    }
}
