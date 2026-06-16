<?php

use App\Http\Middleware\BlockedUser;
use App\Http\Middleware\Sitemapped;
use App\Http\Middleware\TrackCouponCode;
use App\Http\Middleware\TrackReferralCode;
use App\Http\Middleware\UpdateUserLastSeenAt;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Railway terminates TLS at its edge proxy and forwards to the container
        // over http. Trust the proxy so Laravel honors X-Forwarded-Proto and
        // generates https URLs (asset()/@vite, form actions, redirects) — otherwise
        // @vite emits http stylesheet links that the browser blocks as mixed
        // content. The container is only reachable through Railway's proxy, so
        // trusting all proxies is safe here.
        $middleware->trustProxies(at: '*');

        $middleware->appendToGroup('web', [
            BlockedUser::class,
            UpdateUserLastSeenAt::class,
            TrackReferralCode::class,
            TrackCouponCode::class,
        ]);

        $middleware->alias([
            'sitemapped' => Sitemapped::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ]);

        // Inbound Slack webhook posts carry no CSRF token; they are verified instead
        // by the Slack request signature inside the controller.
        $middleware->validateCsrfTokens(except: [
            'webhooks/slack/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {})->create();
