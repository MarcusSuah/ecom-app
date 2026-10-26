<?php

namespace App\Notifications;

use App\Enums\StockStatus;
use App\Models\Product;
use App\Models\ShopProduct;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * An order took a listing's stock into low or out of stock. Sent once per
 * crossing (AlertLowStock), so a seller is not mailed on every sale.
 */
class StockRunningLow extends Notification
{
    public function __construct(public Product|ShopProduct $listing, public StockStatus $status) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'stock.low';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $content = $this->toArray($notifiable);

        return (new MailMessage)
            ->subject($content['title'])
            ->line($content['message'])
            ->action('Update the stock', url($content['link_url']));
    }

    /**
     * @return array{tenant_id: int, title: string, message: string, link_url: string, listing_type: string, listing_id: int}
     */
    public function toArray(object $notifiable): array
    {
        $isOut = $this->status === StockStatus::OUT_OF_STOCK;
        $left = $this->listing instanceof Product
            ? number_format((int) $this->listing->stock)." {$this->listing->unit->value}"
            : number_format($this->listing->stock);

        return [
            'tenant_id' => $this->listing->tenant_id,
            'title' => $isOut ? "{$this->listing->name} is out of stock" : "{$this->listing->name} is running low",
            'message' => $isOut
                ? "Buyers can no longer order {$this->listing->name} ({$left} left). Add stock to sell it again."
                : "Only {$left} of {$this->listing->name} left. Restock soon so buyers can keep ordering.",
            'link_url' => $this->listing instanceof Product
                ? route('supplier.products.edit', $this->listing, absolute: false)
                : route('shop.products.edit', $this->listing, absolute: false),
            'listing_type' => $this->listing->getMorphClass(),
            'listing_id' => $this->listing->id,
        ];
    }
}
