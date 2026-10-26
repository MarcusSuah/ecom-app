<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Messages from the public contact form. Guarded by contact.manage on the routes.
 */
class ContactMessageController extends Controller
{
    public function index(Request $request): Response
    {
        $showHandled = $request->string('status')->toString() === 'handled';

        return Inertia::render('Admin/ContactMessages/Index', [
            'messages' => ContactMessage::query()
                ->when($showHandled, fn ($query) => $query->whereNotNull('handled_at'), fn ($query) => $query->whereNull('handled_at'))
                ->with('handler:id,name')
                ->latest('id')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (ContactMessage $message) => [
                    'id' => $message->id,
                    'name' => $message->name,
                    'email' => $message->email,
                    'phone' => $message->phone,
                    'topic' => $message->topic->label(),
                    'message' => $message->message,
                    'is_member' => $message->user_id !== null,
                    'created_at' => $message->created_at?->toISOString(),
                    'handled_at' => $message->handled_at?->toISOString(),
                    'handled_by' => $message->handler?->name,
                ]),
            'filters' => ['status' => $showHandled ? 'handled' : 'open'],
        ]);
    }

    public function markHandled(Request $request, ContactMessage $contactMessage): RedirectResponse
    {
        if ($contactMessage->handled_at === null) {
            $contactMessage->forceFill(['handled_at' => now(), 'handled_by' => $request->user()?->id])->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Message from {$contactMessage->name} marked as handled."]);

        return back();
    }
}
