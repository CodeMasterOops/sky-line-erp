<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Application;
use Illuminate\Database\QueryException;
use App\Exceptions\ApiDatabaseExceptionRenderer;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('api')
                ->prefix('api/super-admin')
                ->as('api.super-admin.')
                ->group(base_path('routes/api_super_admin.php'));

            Route::middleware('api')
                ->prefix('api/admin')
                ->as('api.admin.')
                ->group(base_path('routes/api_admin.php'));

            Route::middleware('api')
                ->prefix('api/public')
                ->as('api.public.')
                ->group(base_path('routes/api_public.php'));
        }
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'checkRole' => \App\Http\Middleware\CheckRoleMiddleware::class,
            'module' => \App\Http\Middleware\EnsureModuleEnabled::class,
        ]);

        $middleware->throttleApi('api');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Record not found',
                ], 404);
            }
        });

        $exceptions->render(function (QueryException $e, Request $request) {
            return app(ApiDatabaseExceptionRenderer::class)->renderQueryException($e, $request);
        });

        $exceptions->render(function (\Throwable $e, Request $request) {
            return app(ApiDatabaseExceptionRenderer::class)->renderSqlLikeThrowable($e, $request);
        });
    })->create();
