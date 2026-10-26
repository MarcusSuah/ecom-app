<?php

namespace App\Actions\Cart;

use App\Actions\Delivery\DeliveryQuote;
use App\Enums\Fulfilment;
use App\Models\CartItem;
use App\Models\Shop;

/**
 * One shop's part of the cart; checkout turns each into one order.
 */
final readonly class CartGroup
{
    /**
     * @param  list<CartItem>  $items
     * @param  list<string>  $problems
     */
    public function __construct(
        public Shop $shop,
        public array $items,
        public Fulfilment $fulfilment,
        public int $subtotal,
        public ?DeliveryQuote $delivery,
        public array $problems,
    ) {}

    public function deliveryFee(): int
    {
        return $this->fulfilment === Fulfilment::DELIVERY ? ($this->delivery->fee ?? 0) : 0;
    }

    public function total(): int
    {
        return $this->subtotal + $this->deliveryFee();
    }
}
