<?php

declare(strict_types=1);

namespace App\Monitoring\Settings;

use App\Monitoring\Enums\HttpMethod;
use App\Monitoring\Values\AcceptedStatusCodes;

final readonly class MonitorDefaults
{
    public function __construct(
        public HttpMethod $method,
        public AcceptedStatusCodes $acceptedStatusCodes,
    ) {}
}
