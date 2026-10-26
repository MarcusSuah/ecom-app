<?php

namespace App\Enums;

enum PaymentMethod: string
{
    /** Paid through the marketplace and held until the buyer confirms receipt. */
    case ONLINE = 'online';
    /** Paid in cash to the shop when the buyer collects a pickup order. */
    case CASH_ON_PICKUP = 'cash_on_pickup';

    public function label(): string
    {
        return match ($this) {
            self::ONLINE => 'Paid online',
            self::CASH_ON_PICKUP => 'Cash on pickup',
        };
    }
}
