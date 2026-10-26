<?php

use App\Http\Middleware\EnsureAccountApproved;
use App\Http\Middleware\EnsureMarketplaceIsOpen;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureTenantMembership;
use App\Http\Middleware\GuardAccess;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\TrackActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: [
            'appearance',
            'sidebar_state',
        ]);

        // The tenant must be known before route model binding, so bound
        // models are already limited to the current marketplace.
        $middleware->web(prepend: [
            IdentifyTenant::class,
        ], append: [
            // Blocked IPs and blocked accounts stop here; signed-in activity is logged after the response.
            GuardAccess::class,
            TrackActivity::class,
            EnsureTenantMembership::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->api(prepend: [
            IdentifyTenant::class,
        ], append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->preventRequestForgery(except: [
            'webhooks/*',
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'marketplace.open' => EnsureMarketplaceIsOpen::class,
            'platform.admin' => EnsurePlatformAdmin::class,
            'approved' => EnsureAccountApproved::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })
    ->create();
