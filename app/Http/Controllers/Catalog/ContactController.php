<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Contact\SubmitContactMessage;
use App\Enums\ContactTopic;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contact\StoreContactMessageRequest;
use App\Support\CurrentTenant;
use App\Support\PageMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function create(Request $request, CurrentTenant $currentTenant): Response
    {
        $user = $request->user();

        return Inertia::render('content/contact', [
            'meta' => PageMeta::make('Contact us', "Questions about buying, selling or payments on {$currentTenant->require()->name}? Send us a message."),
            'topics' => array_map(fn (ContactTopic $topic) => ['value' => $topic->value, 'label' => $topic->label()], ContactTopic::cases()),
            'defaults' => [
                'name' => $user->name ?? '',
                'email' => $user->email ?? '',
                'topic' => $request->string('topic')->toString(),
            ],
            'supportEmail' => $currentTenant->require()->support_email,
        ]);
    }

    public function store(StoreContactMessageRequest $request, SubmitContactMessage $submitContactMessage): RedirectResponse
    {
        /** @var array{name: string, email: string, phone?: string|null, topic: string, message: string} $input */
        $input = Arr::only($request->validated(), ['name', 'email', 'phone', 'topic', 'message']);

        $submitContactMessage->handle($input, $request->user(), $request->ip());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Thanks, your message was sent. We usually reply within one working day.']);

        return to_route('contact.create');
    }
}
