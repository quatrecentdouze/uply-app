<?php

declare(strict_types=1);

namespace App\Monitoring\Http\Resources;

use App\Monitoring\Values\CheckRecord;
use App\Shared\Time\IsoTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use stdClass;

final class CheckResultResource extends JsonResource
{
    public function __construct(private readonly CheckRecord $check, stdClass $row)
    {
        parent::__construct($row);
    }

    public function toArray(Request $request): array
    {
        return [
            'checked_at' => IsoTime::format($this->check->checkedAt),
            'outcome' => $this->check->outcome->value,
            'failure_kind' => $this->check->failureKind?->value,
            'http_status' => $this->check->httpStatus,
            'response_ms' => $this->check->responseMs,
            'dns_ms' => $this->check->timings->dnsMs,
            'connect_ms' => $this->check->timings->connectMs,
            'tls_ms' => $this->check->timings->tlsMs,
            'ttfb_ms' => $this->check->timings->ttfbMs,
            'error_message' => $this->check->errorMessage,
        ];
    }
}
