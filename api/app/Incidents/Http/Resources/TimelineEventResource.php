<?php

declare(strict_types=1);

namespace App\Incidents\Http\Resources;

use App\Incidents\Models\IncidentEvent;
use App\Shared\Time\IsoTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class TimelineEventResource extends JsonResource
{
    public function __construct(private readonly IncidentEvent $event)
    {
        parent::__construct($event);
    }

    public function toArray(Request $request): array
    {
        return [
            'kind' => $this->event->kind->value,
            'occurred_at' => IsoTime::format($this->event->occurred_at),
            'failure_kind' => $this->event->text('failure_kind'),
            'http_status' => $this->event->number('http_status'),
            'message' => $this->event->text('message'),
            'duration_seconds' => $this->event->number('duration_seconds'),
            'channel_id' => $this->event->text('channel_id'),
            'channel_type' => $this->event->text('channel_type'),
            'alert_kind' => $this->event->text('kind'),
            'attempts' => $this->event->number('attempts'),
        ];
    }
}
