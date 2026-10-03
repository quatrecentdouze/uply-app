<?php

declare(strict_types=1);

namespace App\Providers;

use App\Organizations\Actions\CreateUser;
use App\Shared\OpenApi\RequestRules;
use App\Shared\OpenApi\RequestRulesExtractor;
use Dedoc\Scramble\Configuration\ParametersExtractors;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Http\Controllers\RegisteredUserController;

final class OpenApiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! class_exists(Scramble::class)) {
            return;
        }

        $this->app->singleton(RequestRules::class, fn (): RequestRules => new RequestRules([
            RegisteredUserController::class.'@store' => $this->app->make(CreateUser::class)->rules(),
        ]));

        Scramble::configure()->withParametersExtractors(
            fn (ParametersExtractors $extractors): ParametersExtractors => $extractors->prepend(RequestRulesExtractor::class),
        );
    }
}
