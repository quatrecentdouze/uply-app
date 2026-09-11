<?php

declare(strict_types=1);

use App\Shared\Errors\DomainError;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->redirectGuestsTo('/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport(DomainError::class);
        $exceptions->render(fn (DomainError $domainError): JsonResponse => new JsonResponse([
            'message' => app(Translator::class)->get($domainError->error->messageKey(), $domainError->parameters),
            'code' => $domainError->error->code(),
        ], $domainError->error->httpStatus()));
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->expectsJson()
                || ($request->is('api/*') && ! $request->routeIs('verification.verify')),
        );
    })->create();
