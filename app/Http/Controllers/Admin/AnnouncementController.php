<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AnnouncementAudience;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\MarketplaceAnnouncement;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Notifications admins send to groups of members, and the record of what
 * was sent. Guarded by notifications.send.
 */
class AnnouncementController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Notifications/Index', [
            'announcements' => Announcement::query()
                ->with('sender:id,name')
                ->latest('id')
                ->paginate(20)
                ->through(fn (Announcement $announcement) => [
                    'id' => $announcement->id,
                    'title' => $announcement->title,
                    'body' => $announcement->body,
                    'link_url' => $announcement->link_url,
                    'audience' => $announcement->audience->label(),
                    'recipients_count' => $announcement->recipients_count,
                    'sent_by' => $announcement->sender?->name,
                    'created_at' => $announcement->created_at?->toISOString(),
                ]),
            'audiences' => array_map(fn (AnnouncementAudience $audience) => [
                'value' => $audience->value,
                'label' => $audience->label(),
                'count' => $this->recipients($audience)->count(),
            ], AnnouncementAudience::cases()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:2000'],
            'link_url' => ['nullable', 'string', 'max:500', 'regex:#^(/|https://)#'],
            'audience' => ['required', Rule::enum(AnnouncementAudience::class)],
            'by_email' => ['boolean'],
        ], [
            'link_url.regex' => 'Use a page on this marketplace (starting with /) or a full https:// link.',
        ]);

        $audience = AnnouncementAudience::from($validated['audience']);
        $recipients = $this->recipients($audience)->get();

        $announcement = new Announcement($validated);
        $announcement->forceFill(['sent_by' => $request->user()?->id, 'recipients_count' => $recipients->count()])->save();

        Notification::send($recipients, new MarketplaceAnnouncement($announcement, (bool) ($validated['by_email'] ?? false)));

        Audit::record('create', 'announcements', "Sent \"{$announcement->title}\" to {$audience->label()}", null, ['recipients' => $recipients->count()], $announcement->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Sent to {$recipients->count()} ".($recipients->count() === 1 ? 'member' : 'members').'.']);

        return back();
    }

    /**
     * Members of this marketplace in the audience. Roles are per marketplace,
     * so this never reaches another marketplace's members.
     *
     * @return Builder<User>
     */
    private function recipients(AnnouncementAudience $audience): Builder
    {
        $roles = $audience->roles();

        return User::query()
            ->whereNull('blocked_at')
            ->whereHas('roles', fn (Builder $query) => $roles === null ? $query : $query->whereIn('name', $roles));
    }
}
