<?php

declare(strict_types=1);

namespace App\Providers;

use App\Maintenance\Contracts\MaintenanceSchedule;
use App\Maintenance\Settings\MaintenanceSettings;
use App\Maintenance\Settings\MaintenanceSettingsParser;
use App\Maintenance\Storage\DatabaseMaintenanceSchedule;
use App\Shared\Settings\InvalidSettingException;
use Illuminate\Support\ServiceProvider;

final class MaintenanceServiceProvider extends ServiceProvider
{
    private const string SETTINGS_SOURCE = 'config/maintenance.php';

    public array $bindings = [
        MaintenanceSchedule::class => DatabaseMaintenanceSchedule::class,
    ];

    public function register(): void
    {
        $this->app->singleton(MaintenanceSettings::class, function (): MaintenanceSettings {
            $values = config('maintenance');
            if (! is_array($values)) {
                throw new InvalidSettingException(self::SETTINGS_SOURCE, 'maintenance', 'must return an array');
            }

            return (new MaintenanceSettingsParser)->parse(self::SETTINGS_SOURCE, $values);
        });
    }
}
