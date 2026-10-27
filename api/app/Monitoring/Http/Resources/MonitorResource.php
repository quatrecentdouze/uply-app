<?php

declare(strict_types=1);

namespace App\Monitoring\Http\Resources;

use App\Monitoring\Models\Monitor;
use App\Monitoring\Values\MaintenanceByMonitor;
use App\Monitoring\Values\StatusCodeRange;
use App\Monitoring\Values\UptimeByMonitor;
use App\Shared\Time\IsoTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class MonitorResource extends JsonResource
{
    public function __construct(
        private readonly Monitor $monitor,
        private readonly UptimeByMonitor $uptimes,
        private readonly MaintenanceByMonitor $maintenance,
    ) {
        parent::__construct($monitor);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->monitor->id,
            'name' => $this->monitor->name,
            'url' => $this->monitor->url,
            'method' => $this->monitor->method->value,
            'interval_seconds' => $this->monitor->interval_seconds,
            'timeout_seconds' => $this->monitor->timeout_seconds,
            'accepted_status_codes' => $this->acceptedStatusCodes(),
            'follow_redirects' => $this->monitor->follow_redirects,
            'keyword' => $this->monitor->keyword,
            'keyword_match' => $this->monitor->keyword_match->value,
            'failure_threshold' => $this->monitor->failure_threshold,
            'recovery_threshold' => $this->monitor->recovery_threshold,
            'request_header_names' => $this->monitor->checkRequest()->headerNames(),
            'has_request_body' => $this->monitor->checkRequest()->hasBody(),
            'is_paused' => $this->monitor->is_paused,
            'state' => $this->monitor->state->value,
            'state_changed_at' => IsoTime::format($this->monitor->state_changed_at),
            'last_checked_at' => IsoTime::format($this->monitor->last_checked_at),
            'last_response_ms' => $this->monitor->last_response_ms,
            'tls_expires_at' => IsoTime::format($this->monitor->tls_expires_at),
            'uptime_24h' => $this->uptimes->of($this->monitor->id),
            'maintenance' => $this->maintenance(),
            'created_at' => IsoTime::format($this->monitor->created_at),
            'updated_at' => IsoTime::format($this->monitor->updated_at),
        ];
    }

    private function maintenance(): ?MonitorMaintenanceResource
    {
        $window = $this->maintenance->of($this->monitor->id);

        return $window === null ? null : new MonitorMaintenanceResource($window);
    }

    private function acceptedStatusCodes(): array
    {
        $texts = [];
        foreach ($this->monitor->acceptedStatusCodes()->ranges() as $range) {
            if ($range instanceof StatusCodeRange) {
                $texts[] = $range->toText();
            }
        }

        return $texts;
    }
}
