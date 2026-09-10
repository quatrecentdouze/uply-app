<?php

declare(strict_types=1);

namespace App\Providers;

use App\Organizations\Settings\OrganizationsSettings;
use App\Organizations\Settings\OrganizationsSettingsParser;
use App\Shared\Settings\InvalidSettingException;
use Illuminate\Support\ServiceProvider;

final class OrganizationsServiceProvider extends ServiceProvider
{
    private const string SETTINGS_SOURCE = 'config/organizations.php';

    public function register(): void
    {
        $this->app->singleton(OrganizationsSettings::class, function (): OrganizationsSettings {
            $values = config('organizations');
            if (! is_array($values)) {
                throw new InvalidSettingException(self::SETTINGS_SOURCE, 'organizations', 'must return an array');
            }

            return (new OrganizationsSettingsParser)->parse(self::SETTINGS_SOURCE, $values);
        });
    }
}
