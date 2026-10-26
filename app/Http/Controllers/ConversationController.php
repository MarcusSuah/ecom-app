<?php

namespace App\Http\Controllers;

use App\Actions\Chat\MarkConversationRead;
use App\Actions\Chat\SendMessage;
use App\Actions\Chat\StartConversation;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Models\Rfq;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConversationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $conversations = Conversation::query()
            ->whereHas('participants', fn (Builder $query) => $query->whereKey($user->id))
            ->with(['company:id,name,slug,owner_id', 'participants:id,name'])
            ->withCount(['messages as unread_count' => fn (Builder $query) => $query
                ->where('user_id', '!=', $user->id)
                ->whereNull('read_at')])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->through(fn (Conversation $conversation) => [
                'id' => $conversation->id,
                'subject' => $conversation->subject,
                'with' => $this->counterpartName($conversation, $user),
                'unread_count' => (int) $conversation->getAttribute('unread_count'),
                'last_message_at' => $conversation->last_message_at?->toISOString(),
            ]);

        return Inertia::render('messages/index', ['conversations' => $conversations]);
    }

    /**
     * Polled by the page every few seconds for new messages.
     */
    public function show(Request $request, Conversation $conversation, MarkConversationRead $markRead): Response
    {
        Gate::authorize('view', $conversation);

        $markRead->handle($conversation, $request->user());

        return Inertia::render('messages/show', $this->payload($conversation, $request->user()));
    }

    /**
     * The chat dialog (open-chat-button): starts or reuses the conversation
     * and returns it with the seller's details, without leaving the page.
     */
    public function open(Request $request, StartConversation $startConversation, MarkConversationRead $markRead): JsonResponse
    {
        $conversation = $this->start($request, $startConversation);
        $markRead->handle($conversation, $request->user());

        return response()->json($this->payload($conversation, $request->user()));
    }

    /**
     * New messages for an open chat dialog (polled while it is open).
     */
    public function feed(Request $request, Conversation $conversation, MarkConversationRead $markRead): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $markRead->handle($conversation, $request->user());

        return response()->json($this->payload($conversation, $request->user()));
    }

    /**
     * "Open chat" from a quote, product page or supplier profile.
     */
    public function store(Request $request, StartConversation $startConversation): RedirectResponse
    {
        return to_route('conversations.show', $this->start($request, $startConversation));
    }

    private function start(Request $request, StartConversation $startConversation): Conversation
    {
        $validated = $request->validate([
            'company' => ['required', 'string'],
            'rfq_id' => ['nullable', 'integer'],
            'product_id' => ['nullable', 'integer'],
        ]);

        $company = Company::query()->where('slug', $validated['company'])->firstOrFail();
        $rfq = isset($validated['rfq_id']) ? Rfq::query()->whereKey($validated['rfq_id'])->firstOrFail() : null;
        $product = isset($validated['product_id']) ? Product::query()->active()->whereKey($validated['product_id'])->firstOrFail() : null;

        // Only the RFQ's buyer can open a chat about it, and only with a supplier it was sent to.
        if ($rfq && ($rfq->buyer_id !== $request->user()->id || ! $rfq->recipients()->where('company_id', $company->id)->exists())) {
            abort(403);
        }

        return $startConversation->handle($request->user(), $company, $rfq, $product);
    }

    public function send(Request $request, Conversation $conversation, SendMessage $sendMessage): RedirectResponse|JsonResponse
    {
        Gate::authorize('send', $conversation);

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ], [
            'attachment.mimes' => 'Attach an image (JPG, PNG, WebP) or a PDF.',
            'attachment.max' => 'Attachments must be 10 MB or smaller.',
        ]);

        $sendMessage->handle($conversation, $request->user(), $validated['body'] ?? null, $request->file('attachment'));

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json($this->payload($conversation->refresh(), $request->user()));
        }

        return back();
    }

    public function attachment(Message $message): StreamedResponse
    {
        Gate::authorize('view', $message->conversation ?? abort(404));

        abort_unless($message->attachment_path && Storage::disk(Message::ATTACHMENT_DISK)->exists($message->attachment_path), 404);

        return Storage::disk(Message::ATTACHMENT_DISK)->response($message->attachment_path, $message->attachment_name, [
            'Content-Type' => (string) $message->attachment_mime,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * The conversation, who it is with (the seller's details for a member,
     * the member's for the seller) and its latest 200 messages.
     *
     * @return array<string, mixed>
     */
    private function payload(Conversation $conversation, User $user): array
    {
        $conversation->loadMissing(['company.latestVerification', 'participants:id,name,email,phone', 'rfq:id,title', 'product:id,name,slug']);
        $company = $conversation->company;
        $isSeller = $company->owner_id === $user->id;
        $member = $conversation->participants->firstWhere('id', '!=', $user->id);

        return [
            'conversation' => [
                'id' => $conversation->id,
                'subject' => $conversation->subject,
                'with' => $this->counterpartName($conversation, $user),
                'rfq' => $conversation->rfq ? ['id' => $conversation->rfq->id, 'title' => $conversation->rfq->title] : null,
                'product' => $conversation->product ? ['name' => $conversation->product->name, 'slug' => $conversation->product->slug] : null,
                'url' => route('conversations.show', $conversation),
            ],
            'counterpart' => $isSeller ? [
                'kind' => 'member',
                'name' => $member->name ?? 'Member',
                'email' => $member?->email,
                'phone' => $member?->phone,
            ] : [
                'kind' => $company->type->value,
                'name' => $company->name,
                'slug' => $company->slug,
                'type' => $company->type->label(),
                'location' => collect([$company->city, $company->country ? strtoupper($company->country) : null])->filter()->implode(', ') ?: null,
                'verified' => $company->isVerified(),
                'phone' => $company->phone,
                'email' => $company->email,
                'logo_url' => $company->logoUrl(),
                'profile_url' => $company->isShop() ? route('shops.show', $company) : route('suppliers.show', $company),
            ],
            'messages' => $conversation->messages()
                ->with('sender:id,name')
                ->latest('id')
                ->limit(200)
                ->get()
                ->reverse()
                ->map(fn (Message $message) => [
                    'id' => $message->id,
                    'body' => $message->body,
                    'mine' => $message->user_id === $user->id,
                    'sender' => $message->sender->name,
                    'attachment' => $message->attachment_path ? [
                        'name' => $message->attachment_name,
                        'mime' => $message->attachment_mime,
                        'url' => route('messages.attachment', $message),
                    ] : null,
                    'read_at' => $message->read_at?->toISOString(),
                    'created_at' => $message->created_at?->toISOString(),
                ])->values(),
        ];
    }

    private function counterpartName(Conversation $conversation, User $user): string
    {
        // The member sees the company; the company owner sees the member.
        if ($conversation->company->owner_id !== $user->id) {
            return $conversation->company->name;
        }

        return $conversation->participants->firstWhere('id', '!=', $user->id)->name ?? 'Member';
    }
}
