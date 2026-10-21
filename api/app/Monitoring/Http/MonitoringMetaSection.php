<?php

declare(strict_types=1);

namespace App\Monitoring\Http;

use App\Monitoring\Enums\HttpMethod;
use App\Monitoring\Enums\KeywordMatch;
use App\Monitoring\Enums\StatsWindow;
use App\Monitoring\Http\Requests\MonitorFieldRules;
use App\Monitoring\Settings\MonitoringSettings;
use App\Shared\Meta\MetaSection;

final readonly class MonitoringMetaSection implements MetaSection
{
    public function __construct(private MonitoringSettings $settings) {}

    public function key(): string
    {
        return 'monitoring';
    }

    public function describe(): array
    {
        $confirmation = $this->settings->confirmation;
        $request = $this->settings->request;

        return [
            'intervals' => [
                'allowed_seconds' => $this->allowedSeconds(),
                'default_seconds' => $this->settings->intervals->defaultSeconds,
            ],
            'timeouts' => [
                'min_seconds' => MonitorFieldRules::MIN_TIMEOUT_SECONDS,
                'max_seconds' => $this->settings->probe->maxTimeoutSeconds,
                'default_seconds' => $this->settings->probe->defaultTimeoutSeconds,
            ],
            'methods' => array_map(fn (HttpMethod $method): string => $method->value, HttpMethod::cases()),
            'body_methods' => $this->bodyMethods(),
            'default_method' => $this->settings->monitorDefaults->method->value,
            'default_accepted_status_codes' => $this->settings->monitorDefaults->acceptedStatusCodes->toTexts(),
            'keyword_matches' => array_map(fn (KeywordMatch $match): string => $match->value, KeywordMatch::cases()),
            'default_keyword_match' => $this->settings->monitorDefaults->keywordMatch->value,
            'thresholds' => [
                'min' => $confirmation->minThreshold,
                'max' => $confirmation->maxThreshold,
                'default_failure' => $confirmation->thresholds->failuresToConfirmDown,
                'default_recovery' => $confirmation->thresholds->successesToConfirmUp,
            ],
            'tls' => [
                'expiry_alert_days' => $this->expiryAlertDays(),
            ],
            'refused_header_names' => $this->refusedHeaderNames(),
            'windows' => array_map(fn (StatsWindow $window): string => $window->value, StatsWindow::cases()),
            'limits' => [
                'max_monitors_per_organization' => $this->settings->limits->maxMonitorsPerOrganization,
                'max_accepted_status_ranges' => $this->settings->limits->maxAcceptedStatusRanges,
                'max_request_headers' => $request->maxHeaders,
            ],
            'lengths' => [
                'monitor_name' => ['min' => MonitorFieldRules::NAME_MIN_LENGTH, 'max' => $this->settings->limits->nameMaxLength],
                'url' => ['max' => $this->settings->limits->urlMaxLength],
                'keyword' => ['min' => MonitorFieldRules::KEYWORD_MIN_LENGTH, 'max' => $this->settings->limits->keywordMaxLength],
                'request_header_name' => ['min' => MonitorFieldRules::HEADER_NAME_MIN_LENGTH, 'max' => $request->headerNameMaxLength],
                'request_header_value' => ['max' => $request->headerValueMaxLength],
                'request_body' => ['max' => $request->bodyMaxLength],
            ],
        ];
    }

    private function allowedSeconds(): array
    {
        $allowed = array_values(array_filter($this->settings->intervals->allowedSeconds, is_int(...)));

        return array_map(fn (int $seconds): int => $seconds, $allowed);
    }

    private function bodyMethods(): array
    {
        $methods = array_values(array_filter(HttpMethod::cases(), fn (HttpMethod $method): bool => $method->sendsBody()));

        return array_map(fn (HttpMethod $method): string => $method->value, $methods);
    }

    private function expiryAlertDays(): array
    {
        $days = array_values(array_filter($this->settings->certificate->expiryAlertDays, is_int(...)));

        return array_map(fn (int $day): int => $day, $days);
    }

    private function refusedHeaderNames(): array
    {
        $names = array_values(array_filter($this->settings->request->refusedHeaderNames, is_string(...)));

        return array_map(fn (string $name): string => $name, $names);
    }
}
