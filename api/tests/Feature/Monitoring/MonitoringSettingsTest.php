<?php

declare(strict_types=1);

use App\Monitoring\Settings\MonitoringSettings;
use App\Shared\Settings\InvalidSettingException;

it('resolves the parsed settings from the shipped file', function (): void {
    $settings = app(MonitoringSettings::class);

    expect($settings->dispatch->batchSize)->toBe(500)
        ->and($settings->probe->allowPrivateTargets)->toBeFalse()
        ->and(app(MonitoringSettings::class))->toBe($settings);
});

it('fails with the file and key when the config is invalid', function (): void {
    config(['monitoring.confirmation.recheck_delay_seconds' => 'soon']);
    app()->forgetInstance(MonitoringSettings::class);

    expect(fn () => app(MonitoringSettings::class))
        ->toThrow(InvalidSettingException::class, 'config/monitoring.php: confirmation.recheck_delay_seconds must be an integer of at least 1');
});
