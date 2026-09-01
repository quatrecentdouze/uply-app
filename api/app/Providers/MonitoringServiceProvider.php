<?php

declare(strict_types=1);

namespace App\Providers;

use App\Monitoring\Settings\MonitoringSettings;
use App\Monitoring\Settings\MonitoringSettingsParser;
use App\Shared\Settings\InvalidSettingException;
use Illuminate\Support\ServiceProvider;

final class MonitoringServiceProvider extends ServiceProvider
{
    private const string SETTINGS_SOURCE = 'config/monitoring.php';

    public function register(): void
    {
        $this->app->singleton(MonitoringSettings::class, function (): MonitoringSettings {
            $values = config('monitoring');
            if (! is_array($values)) {
                throw new InvalidSettingException(self::SETTINGS_SOURCE, 'monitoring', 'must return an array');
            }

            return (new MonitoringSettingsParser)->parse(self::SETTINGS_SOURCE, $values);
        });
    }
}
