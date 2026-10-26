<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * A marketplace is open while it is not suspended and its platform
 * subscription grants access. When it is closed, visitors see a notice;
 * the marketplace's admins and platform admins can still get in to sort
 * out billing.
 */
class EnsureMarketplaceIsOpen
{
    public function __construct(private CurrentTenant $currentTenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->currentTenant->require();

        // The platform's own marketplace is not subject to a platform plan.
        if ($tenant->isSystem() || (! $tenant->isSuspended() && $tenant->currentSubscription() !== null)) {
            return $next($request);
        }

        $user = $request->user();

        if ($user && ($user->isPlatformAdmin() || $user->hasRole(Role::ADMIN->value))) {
            return $next($request);
        }

        return Inertia::render('marketplace-unavailable', [
            'marketplace' => $tenant->name,
            'reason' => $tenant->isSuspended() ? 'suspended' : 'billing',
        ])->toResponse($request)->setStatusCode(503);
    }
}
