<?php

use App\Http\Middleware\EnsureNdnAdmin;
use App\Http\Middleware\EnsureWorkerToken;
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
    ->withMiddleware(function (Middleware $middleware) {
        // 근로자 앱 API 스코프 강제 (CLAUDE.md §9)
        $middleware->alias([
            'worker' => EnsureWorkerToken::class,
            'ndn_admin' => EnsureNdnAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
