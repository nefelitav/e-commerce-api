<?php

use App\Exceptions\ProductVersionConflictException;
use App\Http\Middleware\RequireAdmin;
use App\Http\Middleware\RequireAuth;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.required' => RequireAuth::class,
            'admin.required' => RequireAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Safety net: a unique constraint lost a race the service-level check could not see.
        // The raw SQL message is deliberately not exposed.
        $exceptions->render(static fn (UniqueConstraintViolationException $e) => response()->json([
            'success' => false,
            'message' => 'The resource already exists or is being modified concurrently.',
            'error' => 'Conflict',
        ], 409));

        $exceptions->render(static fn (ProductVersionConflictException $e) => response()->json([
            'success' => false,
            'message' => $e->getMessage(),
            'error' => 'Conflict',
        ], 409));
    })->create();
