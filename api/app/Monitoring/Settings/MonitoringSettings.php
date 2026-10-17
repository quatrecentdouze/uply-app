<?php

declare(strict_types=1);

namespace App\Monitoring\Settings;

final readonly class MonitoringSettings
{
    public function __construct(
        public IntervalSettings $intervals,
        public ProbeSettings $probe,
        public MonitorDefaults $monitorDefaults,
        public ConfirmationSettings $confirmation,
        public DispatchSettings $dispatch,
        public RetentionSettings $retention,
        public MonitorLimits $limits,
        public RequestSettings $request,
    ) {}
}
