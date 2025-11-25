<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register middleware aliases
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
        ]);
        
        // Configure rate limiting for API
        $middleware->throttleApi('60,1'); // 60 requests per minute
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle validation exceptions
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors(),
                    'meta' => [
                        'timestamp' => now()->toIso8601String(),
                        'error_code' => 'VALIDATION_ERROR',
                    ]
                ], 422);
            }
        });

        // Handle authentication exceptions
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                    'meta' => [
                        'timestamp' => now()->toIso8601String(),
                        'error_code' => 'UNAUTHENTICATED',
                    ]
                ], 401);
            }
        });

        // Handle not found exceptions
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found',
                    'meta' => [
                        'timestamp' => now()->toIso8601String(),
                        'error_code' => 'NOT_FOUND',
                    ]
                ], 404);
            }
        });

        // Handle access denied exceptions
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'Forbidden',
                    'meta' => [
                        'timestamp' => now()->toIso8601String(),
                        'error_code' => 'FORBIDDEN',
                    ]
                ], 403);
            }
        });

        // Handle general HTTP exceptions
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'An error occurred',
                    'meta' => [
                        'timestamp' => now()->toIso8601String(),
                        'error_code' => 'HTTP_ERROR',
                    ]
                ], $e->getStatusCode());
            }
        });

        // Handle all other exceptions
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') && config('app.debug') === false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Internal server error',
                    'meta' => [
                        'timestamp' => now()->toIso8601String(),
                        'error_code' => 'INTERNAL_ERROR',
                    ]
                ], 500);
            }
        });
    })->create();
