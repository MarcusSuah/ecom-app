<?php

namespace App\Notifications;

use App\Models\Dispute;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DisputeOpened extends Notification
{
    public function __construct(public Dispute $dispute, public int $tenantId) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'dispute.opened';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $content = $this->toArray($notifiable);

        return (new MailMessage)
            ->subject($content['title'])
            ->line($content['message'])
            ->action('Open', url($content['link_url']));
    }

    /**
     * @return array{tenant_id: int, title: string, message: string, link_url: string, dispute_id: int}
     */
    public function toArray(object $notifiable): array
    {
        $order = $this->dispute->order;
        $isSeller = $notifiable instanceof User && $notifiable->id === $order->seller->owner_id;

        return [
            'tenant_id' => $this->tenantId,
            'title' => "Dispute opened on order {$order->number}",
            'message' => "Reason: {$this->dispute->reason->label()}. The payment stays held until it is resolved.",
            'link_url' => $isSeller
                ? route('seller.orders.show', $order, absolute: false)
                : route('admin.disputes.show', $this->dispute, absolute: false),
            'dispute_id' => $this->dispute->id,
        ];
    }
}
