<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role as RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\BlockedIp;
use App\Models\User;
use App\Models\UserActivity;
use App\Services\ActivityLogger;
use App\Support\Audit;
use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What members do on this marketplace: sign-ins (including failed ones),
 * pages they open and changes they submit, with device and network. Admins
 * with activity.manage can block an account or an IP address.
 */
class ActivityController extends Controller
{
    /** @var list<string> */
    private const EVENTS = ['login', 'logout', 'login_failed', 'page_view', 'create', 'update', 'delete', 'password_changed', 'blocked'];

    public function index(Request $request, CurrentTenant $currentTenant): Response
    {
        $search = mb_substr($request->string('search')->trim()->toString(), 0, 100);
        $event = $request->string('event')->toString();
        $suspiciousOnly = $request->boolean('suspicious');

        $activities = UserActivity::query()
            ->where('tenant_id', $currentTenant->require()->id)
            ->with('user:id,name,email,blocked_at')
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('user_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('ip_address', $search)))
            ->when(in_array($event, self::EVENTS, true), fn (Builder $query) => $query->where('event', $event))
            ->when($suspiciousOnly, fn (Builder $query) => $query->where('is_suspicious', true))
            ->latest('created_at')
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (UserActivity $activity) => [
                'id' => $activity->id,
                'event' => $activity->event,
                'description' => $activity->description,
                'user' => $activity->user ? [
                    'id' => $activity->user->id,
                    'name' => $activity->user->name,
                    'email' => $activity->user->email,
                    'is_blocked' => $activity->user->blocked_at !== null,
                ] : null,
                'email' => $activity->email,
                'method' => $activity->method,
                'url' => $activity->url,
                'route_name' => $activity->route_name,
                'ip_address' => $activity->ip_address,
                'ip_blocked' => BlockedIp::isBlocked($activity->ip_address),
                'device' => $activity->device,
                'browser' => $activity->browser,
                'platform' => $activity->platform,
                'location' => implode(', ', array_filter([$activity->city, $activity->region, $activity->country])) ?: null,
                'is_suspicious' => $activity->is_suspicious,
                'created_at' => $activity->created_at?->toISOString(),
            ]);

        return Inertia::render('Admin/Activity/Index', [
            'activities' => $activities,
            'events' => self::EVENTS,
            'blockedIps' => BlockedIp::query()->active()->with('blocker:id,name')->latest('id')->limit(50)->get()
                ->map(fn (BlockedIp $ip) => [
                    'id' => $ip->id,
                    'ip_address' => $ip->ip_address,
                    'reason' => $ip->reason,
                    'blocked_by' => $ip->blocker?->name,
                    'expires_at' => $ip->expires_at?->toISOString(),
                    'created_at' => $ip->created_at?->toISOString(),
                ]),
            'filters' => ['search' => $search, 'event' => $event, 'suspicious' => $suspiciousOnly],
        ]);
    }

    public function blockUser(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']], ['reason.required' => 'Say why the account is blocked.']);

        // Accounts are shared by every marketplace, so only members of this one, and never its staff.
        abort_unless($user->roles()->exists(), 404);

        if ($user->is($request->user()) || $user->isPlatformAdmin() || $user->hasRole([RoleEnum::ADMIN->value, RoleEnum::SUPER_ADMIN->value])) {
            throw ValidationException::withMessages(['reason' => 'You cannot block this account.']);
        }

        $user->forceFill(['blocked_at' => now(), 'blocked_reason' => $validated['reason']])->save();
        // Signed-out everywhere: their sessions end now.
        DB::table('sessions')->where('user_id', $user->id)->delete();

        ActivityLogger::log($request, 'blocked', "Blocked {$user->email}: {$validated['reason']}");
        Audit::record('update', 'users', "Blocked {$user->email}", ['blocked_at' => null], ['blocked_at' => now()->toISOString(), 'reason' => $validated['reason']], $user->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$user->name} is blocked and signed out."]);

        return back();
    }

    public function unblockUser(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->roles()->exists(), 404);

        $user->forceFill(['blocked_at' => null, 'blocked_reason' => null])->save();

        Audit::record('update', 'users', "Unblocked {$user->email}", ['blocked' => true], ['blocked' => false], $user->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$user->name} can sign in again."]);

        return back();
    }

    public function blockIp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ip_address' => ['required', 'ip'],
            'reason' => ['nullable', 'string', 'max:500'],
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        if ($validated['ip_address'] === $request->ip()) {
            throw ValidationException::withMessages(['ip_address' => 'This is your own address; blocking it would lock you out.']);
        }

        BlockedIp::query()->updateOrCreate(['ip_address' => $validated['ip_address']], [
            'reason' => $validated['reason'] ?? null,
            'blocked_by' => $request->user()?->id,
            'expires_at' => isset($validated['days']) ? now()->addDays((int) $validated['days']) : null,
        ]);

        Audit::record('create', 'blocked_ips', "Blocked IP {$validated['ip_address']}", null, $validated);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$validated['ip_address']} is blocked."]);

        return back();
    }

    public function unblockIp(BlockedIp $blockedIp): RedirectResponse
    {
        $blockedIp->delete();

        Audit::record('delete', 'blocked_ips', "Unblocked IP {$blockedIp->ip_address}", ['ip_address' => $blockedIp->ip_address]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$blockedIp->ip_address} is unblocked."]);

        return back();
    }
}
