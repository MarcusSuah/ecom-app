<?php

namespace App\Http\Middleware;

use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Platform screens work across every marketplace, so the tenant found from
 * the host is set aside: tenant-scoped queries are not limited here, and
 * controllers name the tenant they work on explicitly.
 */
class EnsurePlatformAdmin
{
    public function __construct(private CurrentTenant $currentTenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isPlatformAdmin(), 403, 'You do not have permission to access this resource.');

        $this->currentTenant->set(null);

        return $next($request);
    }
}
