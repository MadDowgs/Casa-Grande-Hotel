<?php

use App\Http\Middleware\ApiKey;
use App\Http\Middleware\AdminOnly;
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
        // Behind a reverse proxy / load balancer (Docker, Render, Nginx...) trust X-Forwarded-* headers
        $middleware->trustProxies(at: '*');
        $middleware->alias(['admin' => AdminOnly::class, 'apikey' => ApiKey::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
