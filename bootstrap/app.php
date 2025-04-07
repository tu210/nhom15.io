<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',

        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Đăng ký alias cho middleware của bạn


        $middleware->alias([
            'auth.jwt' => \App\Http\Middleware\CheckUser::class,
            'admin' => \App\Http\Middleware\CheckAdmin::class,
            'auth.jwt.admin' => \App\Http\Middleware\CheckJWTAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
