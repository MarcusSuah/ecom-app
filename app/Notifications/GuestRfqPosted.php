<?php

namespace App\Notifications;

use App\Models\Rfq;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a guest who posted a request without an account: the private
 * link to follow their quotes.
 */
class GuestRfqPosted extends Notification
{
    public function __construct(public Rfq $rfq) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Your request for quotes: {$this->rfq->title}")
            ->greeting('Hello '.($this->rfq->guest_name ?? 'there').',')
            ->line("We sent your request for {$this->rfq->quantity} {$this->rfq->unit->value} of {$this->rfq->title} to matching verified suppliers on {$this->rfq->tenant->name}.")
            ->line('Suppliers usually reply within 48 hours. We will email you each time a quote arrives.')
            ->action('Follow your quotes', route('rfqs.track', $this->rfq->access_token))
            ->line("To accept a quote and order, create an account or sign in with {$this->rfq->guest_email}: the request joins your account automatically.");
    }
}
