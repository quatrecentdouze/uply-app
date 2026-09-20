<?php

declare(strict_types=1);

namespace App\Monitoring\Http\Resources;

use App\Monitoring\Values\MonitorStats;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class MonitorStatsResource extends JsonResource
{
    public function __construct(private readonly MonitorStats $stats)
    {
        parent::__construct($stats);
    }

    public function toArray(Request $request): array
    {
        $summary = $this->stats->summary;
        $timings = $this->stats->timings;

        return [
            'window' => $this->stats->window->value,
            'uptime_percent' => $summary->uptimePercent,
            'checks_total' => $summary->checksTotal,
            'response_ms' => [
                'avg' => $summary->avgResponseMs,
                'min' => $summary->minResponseMs,
                'max' => $summary->maxResponseMs,
            ],
            'series' => SeriesPointResource::collection($this->stats->series),
            'timings' => [
                'dns_ms' => $timings->dnsMs,
                'connect_ms' => $timings->connectMs,
                'tls_ms' => $timings->tlsMs,
                'ttfb_ms' => $timings->ttfbMs,
            ],
        ];
    }
}
