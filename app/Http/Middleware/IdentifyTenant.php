<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Finds the marketplace for the request's host and makes it current:
 *
 *  1. a tenant whose custom domain is the host,
 *  2. "{slug}.{central_domain}",
 *  3. otherwise the fallback tenant (keeps localhost and IP access working).
 *
 * Runs before route model binding, so bound models are already scoped.
 */
class IdentifyTenant
{
    public function __construct(private CurrentTenant $currentTenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->forHost($request->getHost());

        abort_if($tenant === null, 404, 'Marketplace not found.');

        $this->currentTenant->set($tenant);

        return $next($request);
    }

    /**
     * Also used to brand error pages for URLs that match no route.
     */
    public function forHost(string $host): ?Tenant
    {
        $host = Str::lower($host);
        $tenant = Tenant::query()->where('domain', $host)->first();

        if ($tenant) {
            return $tenant;
        }

        $centralDomain = Str::lower((string) config('tenancy.central_domain'));

        if ($centralDomain !== '' && Str::endsWith($host, '.'.$centralDomain)) {
            $slug = Str::beforeLast($host, '.'.$centralDomain);

            return Tenant::query()->where('slug', $slug)->first();
        }

        return Tenant::query()->where('slug', config('tenancy.fallback_tenant'))->first();
    }
}
