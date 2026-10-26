<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;

/**
 * Orders are tenant-scoped, so every Order reaching this policy belongs to
 * the current marketplace. Whether a status move is allowed at all is
 * OrderStateMachine's job; this decides who may ask for it.
 */
class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $this->buys($user, $order) || $this->sells($user, $order);
    }

    /**
     * The invoice or receipt PDF: the buyer and seller, and staff who see
     * the marketplace's reports or settle its disputes.
     */
    public function document(User $user, Order $order): bool
    {
        return $this->view($user, $order) || $user->can('reports.view') || $user->can('disputes.resolve');
    }

    /**
     * The seller moves a paid order through fulfilment.
     */
    public function fulfil(User $user, Order $order): bool
    {
        // A cash-on-pickup order is accepted before anything is paid.
        return $this->sells($user, $order) && ($order->status !== OrderStatus::PENDING_PAYMENT || $order->paysCash());
    }

    /**
     * The buyer pays a pickup order in cash at the shop instead of online.
     */
    public function payCash(User $user, Order $order): bool
    {
        return $this->buys($user, $order) && $order->status === OrderStatus::PENDING_PAYMENT && $order->canPayCash() && ! $order->paysCash();
    }

    /**
     * The shop takes the cash when handing over a cash-on-pickup order.
     */
    public function collectCash(User $user, Order $order): bool
    {
        return $this->sells($user, $order) && $order->paysCash() && $order->status === OrderStatus::READY_FOR_PICKUP;
    }

    /**
     * The seller cancels a paid order they cannot fulfil: the buyer is refunded.
     */
    public function refund(User $user, Order $order): bool
    {
        // Cash orders hold no money: the seller cancels them (cancelCash).
        if ($order->paysCash()) {
            return false;
        }

        return $this->sells($user, $order)
            && ! in_array($order->status, [OrderStatus::PENDING_PAYMENT, OrderStatus::DELIVERED], true)
            && ! $order->status->isFinal();
    }

    public function pay(User $user, Order $order): bool
    {
        return $this->buys($user, $order) && $order->status === OrderStatus::PENDING_PAYMENT;
    }

    /**
     * The buyer drops an order they have not paid for.
     */
    public function cancel(User $user, Order $order): bool
    {
        // Until a cash order is handed over, there is nothing to refund.
        $open = $order->status === OrderStatus::PENDING_PAYMENT
            || ($order->paysCash() && in_array($order->status, [OrderStatus::ACCEPTED, OrderStatus::PREPARING, OrderStatus::READY_FOR_PICKUP], true));

        return $this->buys($user, $order) && $open;
    }

    /**
     * The shop cannot fill a cash-on-pickup order.
     */
    public function cancelCash(User $user, Order $order): bool
    {
        return $this->sells($user, $order) && $order->paysCash() && ! $order->status->isFinal();
    }

    public function confirmReceipt(User $user, Order $order): bool
    {
        return $this->buys($user, $order);
    }

    public function dispute(User $user, Order $order): bool
    {
        return $this->buys($user, $order);
    }

    private function buys(User $user, Order $order): bool
    {
        return $order->buyer_id === $user->id;
    }

    private function sells(User $user, Order $order): bool
    {
        return $order->seller->owner_id === $user->id;
    }
}
