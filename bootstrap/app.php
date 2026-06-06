<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // 🚨 EMERGENCY BREAK: Forces the real hidden error to show up
        $exceptions->render(function (\Throwable $e) {
            header('Content-Type: text/plain', true, 500);
            echo "--- THE REAL HIDDEN ERROR ---\n";
            echo "MESSAGE: " . $e->getMessage() . "\n\n";
            echo "FILE: " . $e->getFile() . " on line " . $e->getLine() . "\n\n";
            echo "TRACE:\n" . $e->getTraceAsString();
            exit(1);
        });
    })->create();