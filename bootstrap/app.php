<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['api', 'auth:api']]
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'ai.rate' => App\Http\Middleware\ThrottleAiRequests::class,
            'tenant' => App\Http\Middleware\EnsureTenantAccess::class,
            'tenant.throttle' => App\Http\Middleware\ThrottleByTenant::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
