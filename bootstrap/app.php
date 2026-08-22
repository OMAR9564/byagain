<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'ensure.active' => EnsureUserIsActive::class,
            'ensure.admin' => EnsureUserIsAdmin::class,
        ]);

        /*
         * Behind a reverse proxy, Laravel must be told to believe the
         * X-Forwarded-* headers. Two things break otherwise, both silently:
         *
         *   - Signed URLs. An unsubscribe link is signed for https://, but the
         *     request arrives at PHP as http://, so the signature never
         *     matches and every unsubscribe returns 403.
         *   - Client IPs. Every visitor appears to come from the proxy, which
         *     turns the per-IP half of the login throttle into a constant.
         *
         * Defaults to loopback, which is correct for nginx on the same host.
         * Set TRUSTED_PROXIES to a comma-separated list, or to `*` when the
         * proxy address is not fixed — safe only if the app itself cannot be
         * reached directly.
         */
        $middleware->trustProxies(at: match ($proxies = (string) env('TRUSTED_PROXIES', '127.0.0.1,::1')) {
            '*' => '*',
            default => array_values(array_filter(array_map(trim(...), explode(',', $proxies)))),
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
