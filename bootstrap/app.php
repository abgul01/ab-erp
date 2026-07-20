<?php

use App\Exceptions\BizException;
use App\Http\Middleware\CheckPermission;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'perm' => CheckPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Uniform error envelope: { "errors": [ { code, message, field } ] }
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e instanceof BizException) {
                return response()->json([
                    'errors' => [['code' => $e->bizCode, 'message' => $e->getMessage(), 'field' => null]],
                ], $e->status);
            }

            if ($e instanceof ValidationException) {
                $errors = [];
                foreach ($e->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $errors[] = ['code' => 'VALIDATION', 'message' => $message, 'field' => $field];
                    }
                }

                return response()->json(['errors' => $errors], 422);
            }

            if ($e instanceof AuthenticationException) {
                return response()->json([
                    'errors' => [['code' => 'UNAUTHENTICATED', 'message' => 'Sesi tidak valid atau telah berakhir.', 'field' => null]],
                ], 401);
            }

            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
            $message = $status === 500 && ! config('app.debug')
                ? 'Terjadi kesalahan pada server.'
                : $e->getMessage();

            return response()->json([
                'errors' => [['code' => 'ERROR', 'message' => $message, 'field' => null]],
            ], $status);
        });
    })->create();
