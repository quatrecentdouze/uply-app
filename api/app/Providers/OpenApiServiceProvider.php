<?php

declare(strict_types=1);

namespace App\Providers;

use App\Organizations\Actions\CreateUser;
use App\Organizations\Actions\ResetUserPassword;
use App\Organizations\Http\Controllers\MeController;
use App\Organizations\Http\Controllers\OrganizationController;
use App\Shared\OpenApi\ArrayFunctionReturnTypes;
use App\Shared\OpenApi\IntegerInValues;
use App\Shared\OpenApi\JsonAnswer;
use App\Shared\OpenApi\JsonBodyParameters;
use App\Shared\OpenApi\MetaResponse;
use App\Shared\OpenApi\OperationMethods;
use App\Shared\OpenApi\RequestRules;
use App\Shared\OpenApi\RequestRulesExtractor;
use Dedoc\Scramble\Configuration\OperationTransformers;
use Dedoc\Scramble\Configuration\ParametersExtractors;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\ConfirmablePasswordController;
use Laravel\Fortify\Http\Controllers\NewPasswordController;
use Laravel\Fortify\Http\Controllers\RecoveryCodeController;
use Laravel\Fortify\Http\Controllers\RegisteredUserController;
use Laravel\Fortify\Http\Controllers\TwoFactorQrCodeController;
use Laravel\Fortify\Http\Controllers\TwoFactorSecretKeyController;

final class OpenApiServiceProvider extends ServiceProvider
{
    private const string CHANNEL_AUTHORIZATION = BroadcastController::class.'@authenticate';

    private const array CHANNEL_AUTHORIZATION_FIELDS = [
        'socket_id' => ['required', 'string'],
        'channel_name' => ['required', 'string'],
    ];

    private const array PASSWORD_DELETES = [
        MeController::class.'@destroy',
        OrganizationController::class.'@destroy',
    ];

    public function boot(): void
    {
        if (! class_exists(Scramble::class)) {
            return;
        }

        $this->app->singleton(RequestRules::class, fn (): RequestRules => new RequestRules([
            RegisteredUserController::class.'@store' => $this->app->make(CreateUser::class)->rules(),
            NewPasswordController::class.'@store' => $this->app->make(ResetUserPassword::class)->rules(),
            self::CHANNEL_AUTHORIZATION => self::CHANNEL_AUTHORIZATION_FIELDS,
        ]));

        $methods = new OperationMethods([self::CHANNEL_AUTHORIZATION => Request::METHOD_POST]);

        Scramble::registerExtension(ArrayFunctionReturnTypes::class);
        Scramble::configure()
            ->resolveOperationMethodsUsing($methods->of(...))
            ->withRuleTransformers(IntegerInValues::class)
            ->withParametersExtractors(
                fn (ParametersExtractors $extractors): ParametersExtractors => $extractors->prepend(RequestRulesExtractor::class),
            )
            ->withOperationTransformers(function (OperationTransformers $transformers): void {
                $transformers->append([
                    MetaResponse::class,
                    new JsonBodyParameters(self::PASSWORD_DELETES),
                    JsonAnswer::object(self::CHANNEL_AUTHORIZATION, Response::HTTP_OK, ['auth' => new StringType]),
                    JsonAnswer::object(AuthenticatedSessionController::class.'@store', Response::HTTP_OK, ['two_factor' => new BooleanType]),
                    JsonAnswer::object(TwoFactorQrCodeController::class.'@show', Response::HTTP_OK, ['svg' => new StringType, 'url' => new StringType]),
                    JsonAnswer::object(TwoFactorSecretKeyController::class.'@show', Response::HTTP_OK, ['secretKey' => new StringType]),
                    JsonAnswer::listOf(RecoveryCodeController::class.'@index', Response::HTTP_OK, new StringType),
                    JsonAnswer::withoutBody(RecoveryCodeController::class.'@store', Response::HTTP_OK),
                    JsonAnswer::withoutBody(ConfirmablePasswordController::class.'@store', Response::HTTP_CREATED),
                    JsonAnswer::withoutBody(RegisteredUserController::class.'@store', Response::HTTP_CREATED),
                ]);
            });
    }
}
