<?php

declare(strict_types=1);

namespace App\Providers;

use App\Organizations\Actions\CreateUser;
use App\Organizations\Actions\ResetUserPassword;
use App\Organizations\Settings\OrganizationsSettings;
use App\Organizations\Settings\OrganizationsSettingsParser;
use App\Shared\Settings\InvalidSettingException;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

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

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute($this->settings()->rateLimits->loginPerMinute)
            ->by(Str::lower($request->string(Fortify::username())->toString()).'|'.$request->ip()));
        RateLimiter::for('two-factor', fn (Request $request): Limit => Limit::perMinute($this->settings()->rateLimits->twoFactorPerMinute)
            ->by($this->pendingLoginId($request).'|'.$request->ip()));
    }

    private function pendingLoginId(Request $request): string
    {
        $loginId = $request->session()->get('login.id');

        return is_string($loginId) || is_int($loginId) ? (string) $loginId : '';
    }

    private function settings(): OrganizationsSettings
    {
        return $this->app->make(OrganizationsSettings::class);
    }
}
