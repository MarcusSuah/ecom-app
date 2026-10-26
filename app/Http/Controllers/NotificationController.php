<?php

namespace App\Http\Controllers;

use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in user's notifications for the current marketplace only.
 */
class NotificationController extends Controller
{
    public function __construct(private CurrentTenant $currentTenant) {}

    public function index(Request $request): Response
    {
        $tenantId = $this->currentTenant->id();
        $unreadOnly = $request->string('filter')->toString() === 'unread';

        $notifications = $request->user()->notifications()
            ->where('data->tenant_id', $tenantId)
            ->when($unreadOnly, fn ($query) => $query->whereNull('read_at'))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (DatabaseNotification $notification) => [
                'id' => $notification->id,
                'type' => $notification->type,
                'title' => $notification->data['title'] ?? '',
                'message' => $notification->data['message'] ?? '',
                'link_url' => $notification->data['link_url'] ?? null,
                'read_at' => $notification->read_at?->toISOString(),
                'created_at' => $notification->created_at?->toISOString(),
            ]);

        return Inertia::render('notifications/index', [
            'notifications' => $notifications,
            'filter' => $unreadOnly ? 'unread' : 'all',
            'counts' => [
                'all' => $request->user()->notifications()->where('data->tenant_id', $tenantId)->count(),
                'unread' => $request->user()->unreadNotifications()->where('data->tenant_id', $tenantId)->count(),
            ],
        ]);
    }

    /**
     * The latest few for the header bell, as JSON.
     */
    public function recent(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $this->currentTenant->id();

        return response()->json([
            'unread' => $user->unreadNotifications()->where('data->tenant_id', $tenantId)->count(),
            'notifications' => $user->notifications()
                ->where('data->tenant_id', $tenantId)
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (DatabaseNotification $notification) => [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => $notification->data['title'] ?? '',
                    'message' => $notification->data['message'] ?? '',
                    'link_url' => $notification->data['link_url'] ?? null,
                    'read_at' => $notification->read_at?->toISOString(),
                    'created_at' => $notification->created_at?->toISOString(),
                ])
                ->values(),
        ]);
    }

    /**
     * Opening a notification marks it read, then follows its link.
     */
    public function open(Request $request, string $notification): RedirectResponse
    {
        $record = $request->user()->notifications()
            ->where('data->tenant_id', $this->currentTenant->id())
            ->whereKey($notification)
            ->firstOrFail();

        $record->markAsRead();
        $link = $record->data['link_url'] ?? null;

        return is_string($link) && str_starts_with($link, '/') && ! str_starts_with($link, '//')
            ? redirect($link)
            : to_route('notifications.index');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()
            ->where('data->tenant_id', $this->currentTenant->id())
            ->update(['read_at' => now()]);

        return back();
    }
}
