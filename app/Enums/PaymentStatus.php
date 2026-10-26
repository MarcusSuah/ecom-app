<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case INITIATED = 'initiated';
    /** Received, kept by the platform until the buyer confirms receipt. */
    case HELD = 'held';
    case RELEASED = 'released';
    case REFUNDED = 'refunded';
    case FAILED = 'failed';

    /**
     * Payments that took the buyer's money: held, released or refunded.
     *
     * @return list<self>
     */
    public static function secured(): array
    {
        return [self::HELD, self::RELEASED, self::REFUNDED];
    }
}
