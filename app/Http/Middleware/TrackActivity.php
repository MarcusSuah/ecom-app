<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs what signed-in users do: every page they open and every change they
 * submit (create / update / delete). Runs after the response is sent.
 */
class TrackActivity
{
    /** Background / noisy routes that aren't worth recording. */
    private const IGNORE = ['login.store', 'logout', 'notifications.recent', 'notifications.read-all', 'currency.update', 'location.update', 'webhooks.*', 'storage.*', 'sitemap', 'robots'];

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $user = $request->user();
        if (! $user || $request->routeIs(...self::IGNORE) || $response->getStatusCode() >= 500) {
            return;
        }

        $method = $request->method();
        if ($method === 'GET') {
            // Only real page visits — not partial reloads, prefetches or downloads.
            if ($request->header('X-Inertia-Partial-Data') || $request->header('Purpose') === 'prefetch' || $request->header('X-Inertia-Prefetch')) {
                return;
            }
            if (! $request->header('X-Inertia') && ! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
                return;
            }
            ActivityLogger::log($request, 'page_view', 'Opened '.$request->path());

            return;
        }

        $event = match ($method) {
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => strtolower($method),
        };
        $failed = $response->getStatusCode() >= 400 || session()->has('errors');
        $route = $request->route()?->getName() ?? $request->path();

        ActivityLogger::log($request, $event, ($failed ? 'Failed: ' : '').str_replace(['.', '-'], [' → ', ' '], $route));
    }
}
