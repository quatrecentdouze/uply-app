<?php

declare(strict_types=1);

namespace App\Providers;

use App\Shared\Settings\InvalidSettingException;
use App\StatusPages\Models\StatusPage;
use App\StatusPages\Policies\StatusPagePolicy;
use App\StatusPages\Settings\StatusPagesSettings;
use App\StatusPages\Settings\StatusPagesSettingsParser;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class StatusPagesServiceProvider extends ServiceProvider
{
    public const string PUBLIC_LIMITER = 'status-pages';

    private const string SETTINGS_SOURCE = 'config/status_pages.php';

    public function register(): void
    {
        $this->app->singleton(StatusPagesSettings::class, function (): StatusPagesSettings {
            $values = config('status_pages');
            if (! is_array($values)) {
                throw new InvalidSettingException(self::SETTINGS_SOURCE, 'status_pages', 'must return an array');
            }

            return (new StatusPagesSettingsParser)->parse(self::SETTINGS_SOURCE, $values);
        });
    }

    public function boot(): void
    {
        Gate::policy(StatusPage::class, StatusPagePolicy::class);

        RateLimiter::for(self::PUBLIC_LIMITER, fn (Request $request): Limit => Limit::perMinute(
            $this->app->make(StatusPagesSettings::class)->public->rateLimitPerMinute,
        )->by((string) $request->ip()));
    }
}
