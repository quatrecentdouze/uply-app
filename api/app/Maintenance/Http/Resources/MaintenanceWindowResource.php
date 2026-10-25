<?php

declare(strict_types=1);

namespace App\Maintenance\Http\Resources;

use App\Maintenance\Models\MaintenanceWindow;
use App\Maintenance\Rules\MaintenanceRules;
use App\Shared\Time\IsoTime;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class MaintenanceWindowResource extends JsonResource
{
    public function __construct(
        private readonly MaintenanceWindow $window,
        private readonly CarbonImmutable $now,
    ) {
        parent::__construct($window);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->window->id,
            'name' => $this->window->name,
            'starts_at' => IsoTime::of($this->window->startsAt()),
            'ends_at' => IsoTime::of($this->window->endsAt()),
            'state' => MaintenanceRules::state($this->window->startsAt(), $this->window->endsAt(), $this->now)->value,
            'monitor_ids' => $this->window->monitorIds(),
            'created_at' => IsoTime::format($this->window->created_at),
        ];
    }
}
