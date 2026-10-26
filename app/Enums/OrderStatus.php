<?php

namespace App\Enums;

/**
 * Every order status, local and wholesale. Which moves are allowed lives in
 * App\Actions\Orders\OrderStateMachine.
 */
enum OrderStatus: string
{
    case PENDING_PAYMENT = 'pending_payment';
    case PAID = 'paid';
    // Local orders.
    case ACCEPTED = 'accepted';
    case PREPARING = 'preparing';
    case READY_FOR_PICKUP = 'ready_for_pickup';
    case OUT_FOR_DELIVERY = 'out_for_delivery';
    // Wholesale orders.
    case IN_PRODUCTION = 'in_production';
    case SHIPPED = 'shipped';
    // Both.
    case DELIVERED = 'delivered';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::PENDING_PAYMENT => 'Awaiting payment',
            self::PAID => 'Paid',
            self::ACCEPTED => 'Accepted',
            self::PREPARING => 'Preparing',
            self::READY_FOR_PICKUP => 'Ready for pickup',
            self::OUT_FOR_DELIVERY => 'Out for delivery',
            self::IN_PRODUCTION => 'In production',
            self::SHIPPED => 'Shipped',
            self::DELIVERED => 'Delivered',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
            self::REFUNDED => 'Refunded',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELLED, self::REFUNDED], true);
    }
}
