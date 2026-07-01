<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // The global api group intentionally does NOT run SubstituteBindings,
        // because route-model binding must resolve AFTER the tenant context is
        // established (otherwise the BelongsToTenant scope would not apply and a
        // user could load another tenant's model by id). Authenticated routes
        // run auth:sanctum -> tenant -> bindings (see routes/api.php).
        $middleware->group('api', [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class.':api',
        ]);

        $middleware->alias([
            'tenant' => \App\Http\Middleware\TenantMiddleware::class,
            'super-admin' => \App\Http\Middleware\EnsureSuperAdmin::class,
            'role' => \App\Http\Middleware\EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();