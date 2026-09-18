<?php

declare(strict_types=1);

namespace App\Monitoring\Http;

use App\Monitoring\Enums\HttpMethod;
use App\Monitoring\Enums\StatsWindow;
use App\Monitoring\Settings\MonitoringSettings;
use App\Shared\Meta\MetaSection;

final readonly class MonitoringMetaSection implements MetaSection
{
    private const int MIN_TIMEOUT_SECONDS = 1;

    private const int MONITOR_NAME_MIN_LENGTH = 1;

    public function __construct(private MonitoringSettings $settings) {}

    public function key(): string
    {
        return 'monitoring';
    }

    public function describe(): array
    {
        return [
            'intervals' => [
                'allowed_seconds' => $this->settings->intervals->allowedSeconds,
                'default_seconds' => $this->settings->intervals->defaultSeconds,
            ],
            'timeouts' => [
                'min_seconds' => self::MIN_TIMEOUT_SECONDS,
                'max_seconds' => $this->settings->probe->maxTimeoutSeconds,
                'default_seconds' => $this->settings->probe->defaultTimeoutSeconds,
            ],
            'methods' => array_map(fn (HttpMethod $method): string => $method->value, HttpMethod::cases()),
            'default_method' => $this->settings->monitorDefaults->method->value,
            'default_accepted_status_codes' => $this->settings->monitorDefaults->acceptedStatusCodes->toTexts(),
            'windows' => array_map(fn (StatsWindow $window): string => $window->value, StatsWindow::cases()),
            'limits' => [
                'max_monitors_per_organization' => $this->settings->limits->maxMonitorsPerOrganization,
                'max_accepted_status_ranges' => $this->settings->limits->maxAcceptedStatusRanges,
            ],
            'lengths' => [
                'monitor_name' => ['min' => self::MONITOR_NAME_MIN_LENGTH, 'max' => $this->settings->limits->nameMaxLength],
                'url' => ['max' => $this->settings->limits->urlMaxLength],
            ],
        ];
    }
}
