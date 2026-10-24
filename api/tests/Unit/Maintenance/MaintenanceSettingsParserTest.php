<?php

declare(strict_types=1);

use App\Maintenance\Settings\MaintenanceSettingsParser;
use App\Shared\Settings\InvalidSettingException;

function shippedMaintenanceConfig(): array
{
    return require dirname(__DIR__, 3).'/config/maintenance.php';
}

it('parses the shipped maintenance file', function (): void {
    $settings = (new MaintenanceSettingsParser)->parse('config/maintenance.php', shippedMaintenanceConfig());

    expect($settings->nameMaxLength)->toBe(100)
        ->and($settings->limits->maxOpenWindowsPerOrganization)->toBe(50)
        ->and($settings->limits->maxMonitorsPerWindow)->toBe(100)
        ->and($settings->bounds->maxDurationHours)->toBe(168)
        ->and($settings->bounds->maxDaysAhead)->toBe(90)
        ->and($settings->bounds->startGraceSeconds)->toBe(60);
});

it('names the file and the key of a wrong value', function (array $override, string $key): void {
    expect(fn () => (new MaintenanceSettingsParser)->parse('config/maintenance.php', array_replace_recursive(shippedMaintenanceConfig(), $override)))
        ->toThrow(InvalidSettingException::class, 'config/maintenance.php')
        ->toThrow(InvalidSettingException::class, $key);
})->with([
    'empty name' => [['lengths' => ['name_max' => 0]], 'lengths.name_max'],
    'no open window' => [['limits' => ['max_open_windows_per_organization' => 0]], 'limits.max_open_windows_per_organization'],
    'monitors as text' => [['limits' => ['max_monitors_per_window' => 'many']], 'limits.max_monitors_per_window'],
    'no duration' => [['bounds' => ['max_duration_hours' => 0]], 'bounds.max_duration_hours'],
    'days in the past' => [['bounds' => ['max_days_ahead' => -1]], 'bounds.max_days_ahead'],
    'negative grace' => [['bounds' => ['start_grace_seconds' => -5]], 'bounds.start_grace_seconds'],
]);
