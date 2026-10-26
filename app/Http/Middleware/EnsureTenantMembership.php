<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accounts are shared by every marketplace. The first time a signed-in user
 * uses a marketplace where they have no role yet, they join it as a buyer.
 */
class EnsureTenantMembership
{
    public function __construct(private CurrentTenant $currentTenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $this->currentTenant->id() !== null && $user->roles()->doesntExist()) {
            $user->assignRole(Role::BUYER->value);
        }

        return $next($request);
    }
}
