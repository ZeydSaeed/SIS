<?php

use App\Domain\Shared\Exceptions\SisDomainException;
use App\Http\Middleware\CorrelationIdMiddleware;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequestTelemetryMiddleware;
use App\Security\Middleware\RequireSchoolContextMiddleware;
use App\Security\Middleware\SchoolContextMiddleware;
use App\Security\Middleware\SecurityHeadersMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->api(prepend: [
            CorrelationIdMiddleware::class,
        ]);

        $middleware->api(append: [
            RequestTelemetryMiddleware::class,
            SecurityHeadersMiddleware::class,
        ]);

        $middleware->throttleApi('api');

        $middleware->alias([
            'require.school.context' => RequireSchoolContextMiddleware::class,
        ]);

        $middleware->web(append: [
            CorrelationIdMiddleware::class,
            SchoolContextMiddleware::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (SisDomainException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            $status = str_contains($exception->errorCode(), 'not_found') ? 404 : 422;

            return response()->json([
                'message' => $exception->getMessage(),
                'error_code' => $exception->errorCode(),
            ], $status);
        });
    })->create();
