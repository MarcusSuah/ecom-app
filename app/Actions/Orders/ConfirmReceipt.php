<?php

namespace App\Actions\Orders;

use App\Actions\Payments\ReleasePayment;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ConfirmReceipt
{
    public function __construct(
        private OrderStateMachine $stateMachine,
        private ReleasePayment $releasePayment,
    ) {}

    /**
     * The buyer says they have the goods. If the seller had not marked the
     * order delivered yet, it is marked now; then the payment is released.
     */
    public function handle(Order $order, User $buyer): Order
    {
        $handedOver = [OrderStatus::READY_FOR_PICKUP, OrderStatus::OUT_FOR_DELIVERY, OrderStatus::SHIPPED, OrderStatus::DELIVERED];

        if (! in_array($order->status, $handedOver, true)) {
            throw ValidationException::withMessages(['order' => 'You can confirm receipt once the seller has sent or handed over the order.']);
        }

        if ($order->hasOpenDispute()) {
            throw ValidationException::withMessages(['order' => 'A dispute is open on this order; an admin will decide.']);
        }

        if ($order->status !== OrderStatus::DELIVERED) {
            $order = $this->stateMachine->transition($order, OrderStatus::DELIVERED, $buyer, 'Buyer confirmed receipt');
        }

        $this->releasePayment->handle($order, $buyer, 'Buyer confirmed receipt');

        return $order->refresh();
    }
}
