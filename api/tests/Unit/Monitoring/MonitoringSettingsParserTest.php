<?php

declare(strict_types=1);

use App\Monitoring\Enums\HttpMethod;
use App\Monitoring\Settings\DispatchTick;
use App\Monitoring\Settings\MonitoringSettingsParser;
use App\Shared\Settings\InvalidSettingException;

function shippedMonitoringConfig(): array
{
    return require dirname(__DIR__, 3).'/config/monitoring.php';
}

it('parses the shipped monitoring file', function (): void {
    $settings = (new MonitoringSettingsParser)->parse('config/monitoring.php', shippedMonitoringConfig());

    expect($settings->intervals->allowedSeconds)->toBe([30, 60, 120, 300, 600, 900, 1800, 3600])
        ->and($settings->intervals->defaultSeconds)->toBe(60)
        ->and($settings->probe->defaultTimeoutSeconds)->toBe(10)
        ->and($settings->probe->maxTimeoutSeconds)->toBe(30)
        ->and($settings->probe->connectTimeoutSeconds)->toBe(5)
        ->and($settings->probe->maxRedirects)->toBe(5)
        ->and($settings->probe->bodyReadCapBytes)->toBe(1024 * 1024)
        ->and($settings->probe->allowPrivateTargets)->toBeFalse()
        ->and($settings->monitorDefaults->method)->toBe(HttpMethod::Get)
        ->and($settings->monitorDefaults->acceptedStatusCodes->toTexts())->toBe(['200-299'])
        ->and($settings->confirmation->thresholds->failuresToConfirmDown)->toBe(2)
        ->and($settings->confirmation->thresholds->successesToConfirmUp)->toBe(2)
        ->and($settings->confirmation->minThreshold)->toBe(1)
        ->and($settings->confirmation->maxThreshold)->toBe(10)
        ->and($settings->confirmation->recheckDelaySeconds)->toBe(20)
        ->and($settings->dispatch->tick)->toBe(DispatchTick::EveryTenSeconds)
        ->and($settings->dispatch->batchSize)->toBe(500)
        ->and($settings->retention->rawResultsDays)->toBe(30)
        ->and($settings->retention->partitionsAheadDays)->toBe(3)
        ->and($settings->retention->hourlyStatsDays)->toBe(400)
        ->and($settings->limits->maxMonitorsPerOrganization)->toBe(100)
        ->and($settings->limits->nameMaxLength)->toBe(100)
        ->and($settings->limits->urlMaxLength)->toBe(2048)
        ->and($settings->limits->maxAcceptedStatusRanges)->toBe(20)
        ->and($settings->request->maxHeaders)->toBe(20)
        ->and($settings->request->headerNameMaxLength)->toBe(100)
        ->and($settings->request->headerValueMaxLength)->toBe(1000)
        ->and($settings->request->bodyMaxLength)->toBe(10000)
        ->and($settings->request->refusedHeaderNames)->toBe(['host', 'content-length', 'transfer-encoding', 'connection', 'keep-alive', 'upgrade', 'te', 'trailer', 'expect', 'proxy-authorization', 'proxy-connection'])
        ->and($settings->request->refusesHeader('Proxy-Authorization'))->toBeTrue()
        ->and($settings->request->refusesHeader('Authorization'))->toBeFalse();
});

it('rejects an invalid setting with its file and key', function (string $key, mixed $value): void {
    $config = shippedMonitoringConfig();
    data_set($config, $key, $value);

    try {
        (new MonitoringSettingsParser)->parse('config/monitoring.php', $config);
        $this->fail("{$key} was accepted");
    } catch (InvalidSettingException $exception) {
        expect($exception->source)->toBe('config/monitoring.php')
            ->and($exception->key)->toBe($key);
    }
})->with([
    'intervals empty' => ['intervals.allowed_seconds', []],
    'intervals not ascending' => ['intervals.allowed_seconds', [60, 30]],
    'intervals duplicated' => ['intervals.allowed_seconds', [30, 30, 60]],
    'intervals with zero' => ['intervals.allowed_seconds', [0, 60]],
    'default interval not allowed' => ['intervals.default_seconds', 45],
    'default timeout zero' => ['probe.default_timeout_seconds', 0],
    'max timeout below default' => ['probe.max_timeout_seconds', 5],
    'connect timeout zero' => ['probe.connect_timeout_seconds', 0],
    'redirects negative' => ['probe.max_redirects', -1],
    'body cap zero' => ['probe.body_read_cap_bytes', 0],
    'user agent blank' => ['probe.user_agent', ''],
    'private targets as text' => ['probe.allow_private_targets', 'yes'],
    'unknown method' => ['monitor_defaults.method', 'delete'],
    'codes not a range' => ['monitor_defaults.accepted_status_codes', ['abc']],
    'codes out of http' => ['monitor_defaults.accepted_status_codes', ['700-799']],
    'codes empty' => ['monitor_defaults.accepted_status_codes', []],
    'failures zero' => ['confirmation.failures_to_confirm_down', 0],
    'successes zero' => ['confirmation.successes_to_confirm_up', 0],
    'recheck zero' => ['confirmation.recheck_delay_seconds', 0],
    'threshold floor zero' => ['confirmation.min_threshold', 0],
    'headers negative' => ['request.max_headers', -1],
    'header value length zero' => ['request.header_value_max_length', 0],
    'body length zero' => ['request.body_max_length', 0],
    'refused names empty' => ['request.refused_header_names', []],
    'refused name in capitals' => ['request.refused_header_names', ['Host']],
    'refused name with a space' => ['request.refused_header_names', ['x forwarded']],
    'threshold ceiling below floor' => ['confirmation.max_threshold', 0],
    'failures above the ceiling' => ['confirmation.failures_to_confirm_down', 11],
    'successes above the ceiling' => ['confirmation.successes_to_confirm_up', 11],
    'tick not a laravel frequency' => ['dispatch.tick_seconds', 7],
    'batch zero' => ['dispatch.batch_size', 0],
    'raw retention zero' => ['retention.raw_results_days', 0],
    'partitions ahead zero' => ['retention.partitions_ahead_days', 0],
    'stats retention zero' => ['retention.hourly_stats_days', 0],
    'monitor limit zero' => ['limits.max_monitors_per_organization', 0],
    'name length zero' => ['limits.name_max_length', 0],
    'url length as text' => ['limits.url_max_length', 'long'],
    'no accepted status range' => ['limits.max_accepted_status_ranges', 0],
]);

it('reports a missing key by its full path', function (): void {
    $config = shippedMonitoringConfig();
    unset($config['probe']['max_redirects']);

    expect(fn () => (new MonitoringSettingsParser)->parse('config/monitoring.php', $config))
        ->toThrow(InvalidSettingException::class, 'config/monitoring.php: probe.max_redirects is missing');
});
