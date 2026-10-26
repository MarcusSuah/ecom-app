<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * New accounts wait for an administrator (MarketplaceSettings
 * require_account_approval). Until approved, every signed-in page sends
 * them to the waiting page; the public storefront stays open to them as
 * it is to guests.
 */
class EnsureAccountApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Only accounts explicitly waiting or turned down are held back.
        if ($user === null || $user->isPlatformAdmin() || ! ($user->isPending() || $user->isRejected())) {
            return $next($request);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            abort(403, 'Your account is waiting for approval.');
        }

        return redirect()->route('account.pending');
    }
}
