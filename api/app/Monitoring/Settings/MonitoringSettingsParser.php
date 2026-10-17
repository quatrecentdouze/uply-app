<?php

declare(strict_types=1);

namespace App\Monitoring\Settings;

use App\Monitoring\Enums\HttpMethod;
use App\Monitoring\Values\AcceptedStatusCodes;
use App\Monitoring\Values\ConfirmationThresholds;
use App\Shared\Settings\SettingsReader;
use InvalidArgumentException;

final class MonitoringSettingsParser
{
    private const string LOWERCASE_HEADER_NAME = "/^[a-z0-9!#$%&'*+.^_`|~-]+$/";

    public function parse(string $source, array $values): MonitoringSettings
    {
        $reader = new SettingsReader($source, $values);

        return new MonitoringSettings(
            intervals: $this->intervals($reader),
            probe: $this->probe($reader),
            monitorDefaults: $this->monitorDefaults($reader),
            confirmation: $this->confirmation($reader),
            dispatch: $this->dispatch($reader),
            retention: $this->retention($reader),
            limits: new MonitorLimits(
                maxMonitorsPerOrganization: $reader->integer('limits.max_monitors_per_organization', 1),
                nameMaxLength: $reader->integer('limits.name_max_length', 1),
                urlMaxLength: $reader->integer('limits.url_max_length', 1),
                maxAcceptedStatusRanges: $reader->integer('limits.max_accepted_status_ranges', 1),
            ),
            request: $this->request($reader),
        );
    }

    private function request(SettingsReader $reader): RequestSettings
    {
        $refused = $reader->textList('request.refused_header_names');
        foreach ($refused as $name) {
            if (! is_string($name) || preg_match(self::LOWERCASE_HEADER_NAME, $name) !== 1) {
                throw $reader->invalid('request.refused_header_names', 'must only hold lowercase header names');
            }
        }

        return new RequestSettings(
            maxHeaders: $reader->integer('request.max_headers', 0),
            headerNameMaxLength: $reader->integer('request.header_name_max_length', 1),
            headerValueMaxLength: $reader->integer('request.header_value_max_length', 1),
            bodyMaxLength: $reader->integer('request.body_max_length', 1),
            refusedHeaderNames: $refused,
        );
    }

    private function intervals(SettingsReader $reader): IntervalSettings
    {
        $allowed = $reader->integerList('intervals.allowed_seconds', 1);
        $previous = 0;
        foreach ($allowed as $seconds) {
            if ($seconds <= $previous) {
                throw $reader->invalid('intervals.allowed_seconds', 'must be ascending without duplicates');
            }
            $previous = $seconds;
        }
        $intervals = new IntervalSettings($allowed, $reader->integer('intervals.default_seconds', 1));
        if (! $intervals->allows($intervals->defaultSeconds)) {
            throw $reader->invalid('intervals.default_seconds', 'must be one of intervals.allowed_seconds');
        }

        return $intervals;
    }

    private function probe(SettingsReader $reader): ProbeSettings
    {
        $defaultTimeout = $reader->integer('probe.default_timeout_seconds', 1);

        return new ProbeSettings(
            defaultTimeoutSeconds: $defaultTimeout,
            maxTimeoutSeconds: $reader->integer('probe.max_timeout_seconds', $defaultTimeout),
            connectTimeoutSeconds: $reader->integer('probe.connect_timeout_seconds', 1),
            maxRedirects: $reader->integer('probe.max_redirects', 0),
            bodyReadCapBytes: $reader->integer('probe.body_read_cap_bytes', 1),
            userAgent: $reader->text('probe.user_agent'),
            allowPrivateTargets: $reader->boolean('probe.allow_private_targets'),
        );
    }

    private function monitorDefaults(SettingsReader $reader): MonitorDefaults
    {
        $method = HttpMethod::tryFrom($reader->text('monitor_defaults.method'));
        if ($method === null) {
            $names = implode(', ', array_map(static fn (HttpMethod $case): string => $case->value, HttpMethod::cases()));
            throw $reader->invalid('monitor_defaults.method', "must be one of {$names}");
        }
        $codeTexts = $reader->textList('monitor_defaults.accepted_status_codes');
        try {
            $codes = AcceptedStatusCodes::parse($codeTexts);
        } catch (InvalidArgumentException $exception) {
            throw $reader->invalid('monitor_defaults.accepted_status_codes', $exception->getMessage());
        }

        return new MonitorDefaults($method, $codes);
    }

    private function confirmation(SettingsReader $reader): ConfirmationSettings
    {
        $minThreshold = $reader->integer('confirmation.min_threshold', 1);
        $confirmation = new ConfirmationSettings(
            thresholds: new ConfirmationThresholds(
                failuresToConfirmDown: $reader->integer('confirmation.failures_to_confirm_down', 1),
                successesToConfirmUp: $reader->integer('confirmation.successes_to_confirm_up', 1),
            ),
            minThreshold: $minThreshold,
            maxThreshold: $reader->integer('confirmation.max_threshold', $minThreshold),
            recheckDelaySeconds: $reader->integer('confirmation.recheck_delay_seconds', 1),
        );
        foreach (['failures_to_confirm_down' => $confirmation->thresholds->failuresToConfirmDown, 'successes_to_confirm_up' => $confirmation->thresholds->successesToConfirmUp] as $key => $threshold) {
            if (! $confirmation->allowsThreshold($threshold)) {
                throw $reader->invalid("confirmation.{$key}", 'must be between confirmation.min_threshold and confirmation.max_threshold');
            }
        }

        return $confirmation;
    }

    private function dispatch(SettingsReader $reader): DispatchSettings
    {
        $tick = DispatchTick::tryFrom($reader->integer('dispatch.tick_seconds', 1));
        if ($tick === null) {
            $seconds = implode(', ', array_map(static fn (DispatchTick $case): int => $case->value, DispatchTick::cases()));
            throw $reader->invalid('dispatch.tick_seconds', "must be one of {$seconds}");
        }

        return new DispatchSettings($tick, $reader->integer('dispatch.batch_size', 1));
    }

    private function retention(SettingsReader $reader): RetentionSettings
    {
        return new RetentionSettings(
            rawResultsDays: $reader->integer('retention.raw_results_days', 1),
            partitionsAheadDays: $reader->integer('retention.partitions_ahead_days', 1),
            hourlyStatsDays: $reader->integer('retention.hourly_stats_days', 1),
        );
    }
}
