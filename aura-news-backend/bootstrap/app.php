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
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(\Illuminate\Http\Middleware\HandleCors::class);
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);
        // --- ↓↓↓ 在這裡加入 CSRF 豁免設定 ↓↓↓ ---
        $middleware->validateCsrfTokens(except: [
            'api/*', // 豁免所有 /api/ 開頭的路徑
        ]);
        // --- ↑↑↑ CSRF 豁免設定結束 ↑↑↑ ---

        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
        
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
