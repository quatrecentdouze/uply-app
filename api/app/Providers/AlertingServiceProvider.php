<?php

declare(strict_types=1);

namespace App\Providers;

use App\Alerting\Settings\AlertingSettings;
use App\Alerting\Settings\AlertingSettingsParser;
use App\Shared\Settings\InvalidSettingException;
use Illuminate\Support\ServiceProvider;

final class AlertingServiceProvider extends ServiceProvider
{
    private const string SETTINGS_SOURCE = 'config/alerting.php';

    public function register(): void
    {
        $this->app->singleton(AlertingSettings::class, function (): AlertingSettings {
            $values = config('alerting');
            if (! is_array($values)) {
                throw new InvalidSettingException(self::SETTINGS_SOURCE, 'alerting', 'must return an array');
            }

            return (new AlertingSettingsParser)->parse(self::SETTINGS_SOURCE, $values);
        });
    }
}
