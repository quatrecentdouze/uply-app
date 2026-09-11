<?php

declare(strict_types=1);

namespace App\Providers;

use App\Organizations\Actions\CreateUser;
use App\Organizations\Actions\ResetUserPassword;
use App\Organizations\Messages\AccountMailBuilder;
use App\Organizations\Models\User;
use App\Organizations\Settings\OrganizationsSettings;
use App\Organizations\Settings\OrganizationsSettingsParser;
use App\Shared\Settings\InvalidSettingException;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Fortify\Fortify;

final class OrganizationsServiceProvider extends ServiceProvider
{
    private const string SETTINGS_SOURCE = 'config/organizations.php';

    private const string PASSWORD_CONFIRMATION_MIDDLEWARE = 'password.confirm';

    private const array FORTIFY_ROUTES_FOR_VERIFIED_USERS = [
        'password.confirmation',
        'password.confirm.store',
        'two-factor.enable',
        'two-factor.confirm',
        'two-factor.disable',
        'two-factor.qr-code',
        'two-factor.secret-key',
        'two-factor.recovery-codes',
        'two-factor.regenerate-recovery-codes',
    ];

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

        VerifyEmail::toMailUsing(fn (mixed $notifiable, string $url): MailMessage => $this->mails()
            ->verification($this->user($notifiable)->name, $url, config()->integer('auth.verification.expire')));
        ResetPassword::toMailUsing(fn (mixed $notifiable, string $token): MailMessage => $this->mails()
            ->passwordReset($this->user($notifiable)->name, $this->user($notifiable)->email, $token, config()->integer('auth.passwords.users.expire')));

        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute($this->settings()->rateLimits->loginPerMinute)
            ->by(Str::lower($request->string(Fortify::username())->toString()).'|'.$request->ip()));
        RateLimiter::for('two-factor', fn (Request $request): Limit => Limit::perMinute($this->settings()->rateLimits->twoFactorPerMinute)
            ->by($this->pendingLoginId($request).'|'.$request->ip()));

        $this->app->booted(fn () => $this->requireVerifiedEmailOnFortifyAccountRoutes());
    }

    private function requireVerifiedEmailOnFortifyAccountRoutes(): void
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! in_array($route->getName(), self::FORTIFY_ROUTES_FOR_VERIFIED_USERS, true)) {
                continue;
            }
            $middleware = array_filter((array) ($route->action['middleware'] ?? []), 'is_string');
            $confirmsPassword = in_array(self::PASSWORD_CONFIRMATION_MIDDLEWARE, $middleware, true);
            $route->action['middleware'] = [
                ...array_diff($middleware, [self::PASSWORD_CONFIRMATION_MIDDLEWARE]),
                'verified',
                ...($confirmsPassword ? [self::PASSWORD_CONFIRMATION_MIDDLEWARE] : []),
            ];
        }
    }

    private function pendingLoginId(Request $request): string
    {
        $loginId = $request->session()->get('login.id');

        return is_string($loginId) || is_int($loginId) ? (string) $loginId : '';
    }

    private function user(mixed $notifiable): User
    {
        return $notifiable instanceof User ? $notifiable : throw new InvalidArgumentException('account mails go to users only');
    }

    private function mails(): AccountMailBuilder
    {
        return $this->app->make(AccountMailBuilder::class);
    }

    private function settings(): OrganizationsSettings
    {
        return $this->app->make(OrganizationsSettings::class);
    }
}
