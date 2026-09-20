<?php

declare(strict_types=1);

namespace App\Monitoring\Http\Resources;

use App\Monitoring\Values\SeriesPoint;
use App\Shared\Time\IsoTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SeriesPointResource extends JsonResource
{
    public function __construct(private readonly SeriesPoint $point)
    {
        parent::__construct($point);
    }

    public function toArray(Request $request): array
    {
        return [
            'start' => IsoTime::format($this->point->start),
            'uptime_percent' => $this->point->uptimePercent,
            'avg_response_ms' => $this->point->avgResponseMs,
        ];
    }
}
