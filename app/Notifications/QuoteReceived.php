<?php

namespace App\Notifications;

use App\Models\Quote;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A supplier quoted on the buyer's request. Guests get the email only,
 * with their private tracking link.
 */
class QuoteReceived extends Notification
{
    public function __construct(public Quote $quote) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? ['database', 'mail'] : ['mail'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'quote.received';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $content = $this->toArray($notifiable);

        return (new MailMessage)
            ->subject($content['title'])
            ->line($content['message'])
            ->action('Compare quotes', $this->link($notifiable, absolute: true));
    }

    /**
     * @return array{tenant_id: int, title: string, message: string, link_url: string, quote_id: int}
     */
    public function toArray(object $notifiable): array
    {
        $rfq = $this->quote->rfq;

        return [
            'tenant_id' => $this->quote->tenant_id,
            'title' => "New quote for {$rfq->title}",
            'message' => "{$this->quote->company->name} sent a quote with tier prices and a lead time of {$this->quote->leadTimeLabel()}.",
            'link_url' => $this->link($notifiable, absolute: false),
            'quote_id' => $this->quote->id,
        ];
    }

    private function link(object $notifiable, bool $absolute): string
    {
        $rfq = $this->quote->rfq;

        return $notifiable instanceof User || $rfq->access_token === null
            ? route('rfqs.show', $rfq, $absolute)
            : route('rfqs.track', $rfq->access_token, $absolute);
    }
}
