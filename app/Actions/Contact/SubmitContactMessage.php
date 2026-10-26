<?php

namespace App\Actions\Contact;

use App\Models\ContactMessage;
use App\Models\User;
use App\Notifications\ContactMessageReceived;
use Illuminate\Support\Facades\Notification;

class SubmitContactMessage
{
    /**
     * Store the message on the current marketplace and tell the people who
     * answer them there (contact.manage).
     *
     * @param  array{name: string, email: string, phone?: string|null, topic: string, message: string}  $input
     */
    public function handle(array $input, ?User $sender, ?string $ipAddress): ContactMessage
    {
        $contactMessage = ContactMessage::query()->create([
            ...$input,
            'user_id' => $sender?->id,
            'ip_address' => $ipAddress,
        ]);

        // Roles are per marketplace, so this only finds this marketplace's staff.
        $recipients = User::query()
            ->whereHas('roles.permissions', fn ($query) => $query->where('name', 'contact.manage'))
            ->get();

        Notification::send($recipients, new ContactMessageReceived($contactMessage));

        return $contactMessage;
    }
}
