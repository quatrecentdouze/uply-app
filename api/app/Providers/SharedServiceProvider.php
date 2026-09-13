<?php

declare(strict_types=1);

namespace App\Providers;

use App\Shared\Clock;
use App\Shared\Settings\ApiSettings;
use App\Shared\Settings\ApiSettingsParser;
use App\Shared\Settings\InvalidSettingException;
use App\Shared\Settings\PaginationSettings;
use App\Shared\SystemClock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

final class SharedServiceProvider extends ServiceProvider
{
    private const string API_SETTINGS_SOURCE = 'config/api.php';

    public function register(): void
    {
        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->singleton(ApiSettings::class, function (): ApiSettings {
            $values = config('api');
            if (! is_array($values)) {
                throw new InvalidSettingException(self::API_SETTINGS_SOURCE, 'api', 'must return an array');
            }

            return (new ApiSettingsParser)->parse(self::API_SETTINGS_SOURCE, $values);
        });
        $this->app->bind(PaginationSettings::class, fn (): PaginationSettings => $this->app->make(ApiSettings::class)->pagination);
    }

    public function boot(): void
    {
        Date::use(CarbonImmutable::class);
        Model::shouldBeStrict(! $this->app->isProduction());
        DB::prohibitDestructiveCommands($this->app->isProduction());
    }
}
