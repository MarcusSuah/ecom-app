<?php

namespace App\Notifications;

use App\Models\Company;
use App\Models\Rfq;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RfqReceived extends Notification
{
    public function __construct(public Rfq $rfq, public Company $company) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'rfq.received';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New request for quotation: {$this->rfq->title}")
            ->greeting("Hello {$this->company->name},")
            ->line("A buyer on {$this->rfq->tenant->name} needs {$this->rfq->quantity} {$this->rfq->unit->value} of {$this->rfq->title}, delivered to {$this->rfq->deliver_city}.")
            ->line('The request closes on '.$this->rfq->expires_at->format('j F Y').'.')
            ->action('View the request', route('supplier.rfqs.show', $this->rfq));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tenant_id' => $this->rfq->tenant_id,
            'title' => 'New request for quotation',
            'message' => "{$this->rfq->quantity} {$this->rfq->unit->value} of {$this->rfq->title}, delivered to {$this->rfq->deliver_city}.",
            'link_url' => route('supplier.rfqs.show', $this->rfq, absolute: false),
            'rfq_id' => $this->rfq->id,
        ];
    }
}
