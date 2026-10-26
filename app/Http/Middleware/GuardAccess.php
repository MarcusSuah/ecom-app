<?php

namespace App\Http\Middleware;

use App\Models\BlockedIp;
use App\Services\ActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stops blocked IP addresses from reaching the site at all, and signs out
 * users whose account an administrator has blocked.
 */
class GuardAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (BlockedIp::isBlocked($request->ip())) {
            abort(403, 'Access from your network has been blocked. Please contact the marketplace team.');
        }

        $user = $request->user();
        if ($user && ($user->blocked_at || $user->is_active === false)) {
            ActivityLogger::log($request, 'blocked', 'Blocked account tried to use the site', $user);
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Your account has been blocked. Please contact the marketplace team.');
        }

        return $next($request);
    }
}
