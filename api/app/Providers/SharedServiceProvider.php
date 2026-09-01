<?php

declare(strict_types=1);

namespace App\Providers;

use App\Shared\Clock;
use App\Shared\SystemClock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

final class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Clock::class, SystemClock::class);
    }

    public function boot(): void
    {
        Date::use(CarbonImmutable::class);
        Model::shouldBeStrict(! $this->app->isProduction());
        DB::prohibitDestructiveCommands($this->app->isProduction());
    }
}
