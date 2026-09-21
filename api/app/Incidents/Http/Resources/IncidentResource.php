<?php

declare(strict_types=1);

namespace App\Incidents\Http\Resources;

use App\Incidents\Models\Incident;
use App\Incidents\Values\MonitorNames;
use App\Shared\Time\IsoTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class IncidentResource extends JsonResource
{
    public function __construct(
        private readonly Incident $incident,
        private readonly MonitorNames $names,
    ) {
        parent::__construct($incident);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->incident->id,
            'monitor' => [
                'id' => $this->incident->monitor_id,
                'name' => $this->names->of($this->incident->monitor_id),
            ],
            'started_at' => IsoTime::format($this->incident->started_at),
            'resolved_at' => IsoTime::format($this->incident->resolved_at),
            'duration_seconds' => $this->incident->durationSeconds(),
            'cause' => [
                'failure_kind' => $this->incident->cause_failure_kind,
                'http_status' => $this->incident->cause_http_status,
                'message' => $this->incident->cause_message,
            ],
        ];
    }
}
