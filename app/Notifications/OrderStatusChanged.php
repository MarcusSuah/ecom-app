<?php

namespace App\Notifications;

use App\Actions\Reports\RenderDocument;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * In-app and email notice to the buyer and the seller on every status
 * change. SMS for payment and delivery is sent by OrderStateMachine. The
 * buyer's "paid" email carries the receipt PDF.
 */
class OrderStatusChanged extends Notification
{
    public function __construct(public Order $order) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'order.status';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $content = $this->content($notifiable);

        $mail = (new MailMessage)
            ->subject($content['title'])
            ->line($content['message'])
            ->action('View the order', url($content['link_url']));

        $paidNow = $this->order->paysCash() ? $this->order->status === OrderStatus::COMPLETED : $this->order->status === OrderStatus::PAID;

        if ($paidNow && $this->isBuyer($notifiable)) {
            $mail->line('Your receipt is attached.')
                ->attachData(app(RenderDocument::class)->order($this->order)->output(), "{$this->order->number}.pdf", ['mime' => 'application/pdf']);
        }

        return $mail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tenant_id' => $this->order->tenant_id,
            ...$this->content($notifiable),
            'order_id' => $this->order->id,
        ];
    }

    /**
     * @return array{title: string, message: string, link_url: string}
     */
    private function content(object $notifiable): array
    {
        $isBuyer = $this->isBuyer($notifiable);
        $status = $this->order->status->label();

        return [
            'title' => "Order {$this->order->number}: {$status}",
            'message' => ($isBuyer
                ? "Your order from {$this->order->seller->name} is now {$status}."
                : "Order {$this->order->number} is now {$status}.")
                .($this->order->paysCash() && ! $this->order->status->isFinal() ? ' Payment: cash on pickup ('.Money::format($this->order->total, $this->order->currency).').' : ''),
            'link_url' => $isBuyer
                ? route('orders.show', $this->order, absolute: false)
                : route('seller.orders.show', $this->order, absolute: false),
        ];
    }

    private function isBuyer(object $notifiable): bool
    {
        return $notifiable instanceof User && $notifiable->id === $this->order->buyer_id;
    }
}
