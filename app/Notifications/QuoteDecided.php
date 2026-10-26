<?php

namespace App\Notifications;

use App\Enums\QuoteStatus;
use App\Models\Quote;
use Illuminate\Notifications\Notification;

class QuoteDecided extends Notification
{
    public function __construct(public Quote $quote) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return $this->accepted() ? 'quote.accepted' : 'quote.rejected';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tenant_id' => $this->quote->tenant_id,
            'title' => $this->accepted() ? 'Your quote was accepted' : 'The buyer chose another quote',
            'message' => "Request: {$this->quote->rfq->title}.",
            'link_url' => route('supplier.rfqs.show', $this->quote->rfq_id, absolute: false),
            'quote_id' => $this->quote->id,
        ];
    }

    private function accepted(): bool
    {
        return $this->quote->status === QuoteStatus::ACCEPTED;
    }
}
