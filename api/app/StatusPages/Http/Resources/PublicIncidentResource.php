<?php

declare(strict_types=1);

namespace App\StatusPages\Http\Resources;

use App\StatusPages\Values\PublicIncident;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PublicIncidentResource extends JsonResource
{
    public function __construct(private readonly PublicIncident $incident)
    {
        parent::__construct($incident);
    }

    public function toArray(Request $request): array
    {
        return [
            'display_name' => $this->incident->displayName,
            'started_at' => $this->incident->startedAt,
            'resolved_at' => $this->incident->resolvedAt,
            'duration_seconds' => $this->incident->durationSeconds,
        ];
    }
}
