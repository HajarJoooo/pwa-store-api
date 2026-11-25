<?php

use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException as SpatieUnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->append(SecurityHeaders::class);

        $middleware->api(prepend: [
            SetLocale::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (Throwable $e): ?bool {
            if (env('VERCEL')) {
                error_log(sprintf('[ERROR] %s: %s (%s:%d)', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));

                return false;
            }

            return null;
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                App::setLocale(SetLocale::determineLocale($request));

                return response()->json([
                    'success' => false,
                    'message' => __('messages.unauthenticated'),
                    'data' => null,
                    'errors' => null,
                ], 401);
            }
        });

        $exceptions->render(function (AccessDeniedHttpException|AuthorizationException|SpatieUnauthorizedException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                App::setLocale(SetLocale::determineLocale($request));

                $customMessage = $e->getMessage();
                $message = (! empty($customMessage) && ! str_contains($customMessage, 'This action is unauthorized') && ! str_contains($customMessage, 'User does not have'))
                    ? $customMessage
                    : __('messages.unauthorized');

                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'data' => null,
                    'errors' => null,
                ], 403);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                App::setLocale(SetLocale::determineLocale($request));

                return response()->json([
                    'success' => false,
                    'message' => __('messages.not_found'),
                    'data' => null,
                    'errors' => null,
                ], 404);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                App::setLocale(SetLocale::determineLocale($request));

                return response()->json([
                    'success' => false,
                    'message' => __('messages.validation_failed'),
                    'data' => null,
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                App::setLocale(SetLocale::determineLocale($request));

                return response()->json([
                    'success' => false,
                    'message' => __('messages.too_many_requests'),
                    'data' => null,
                    'errors' => null,
                ], 429);
            }
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                App::setLocale(SetLocale::determineLocale($request));

                if (! config('app.debug')) {
                    return response()->json([
                        'success' => false,
                        'message' => __('messages.server_error'),
                        'data' => null,
                        'errors' => null,
                    ], 500);
                }
            }
        });
    })->create();

if ($storagePath = env('APP_STORAGE')) {
    $app->useStoragePath($storagePath);
}

return $app;
