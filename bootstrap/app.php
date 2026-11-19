<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR |
                     Request::HEADER_X_FORWARDED_HOST |
                     Request::HEADER_X_FORWARDED_PORT |
                     Request::HEADER_X_FORWARDED_PROTO |
                     Request::HEADER_X_FORWARDED_AWS_ELB
        );

        $middleware->validateCsrfTokens(except: [
            'api/*', // Sanctum token-based, no CSRF needed
        ]);

        // Force JSON for all api/* requests (401/403/404/429/500 all become JSON)
        $middleware->statefulApi(); // Sanctum stateful API support

        $middleware->appendToGroup('web', [
            \App\Http\Middleware\EnsureUserIsActive::class,
        ]);

        $middleware->alias([
            'abilities'       => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability'         => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
            'customer.active' => \App\Http\Middleware\CheckCustomerActive::class,
            'role'            => \App\Http\Middleware\EnsureUserRole::class,
            'active'          => \App\Http\Middleware\EnsureUserIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // All api/* paths → always JSON response
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Standardized clean JSON error messages for API (no internal class / stack leaks)
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Tidak terautentikasi. Silakan login terlebih dahulu.'], 401);
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Data tidak ditemukan.'], 404);
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Akses ditolak.'], 403);
            }
        });

        $exceptions->render(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Terlalu banyak permintaan. Silakan coba lagi nanti.'], 429);
            }
        });

        // Sanitize server errors (no stack trace)
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($e instanceof \Illuminate\Validation\ValidationException || $e instanceof \Illuminate\Http\Exceptions\HttpResponseException) {
                return null;
            }

            if ($request->is('api/*')) {
                $code = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                if ($code >= 500) {
                    return response()->json(['message' => 'Terjadi kesalahan pada server.'], 500);
                }
            } elseif (! config('app.debug') && ! $request->expectsJson()) {
                $code = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                if ($code === 500) {
                    return response()->view('errors.500', [], 500);
                }
            }
        });
    })->create();
