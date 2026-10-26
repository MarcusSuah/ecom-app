<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    /** Waiting for the first payment; the subscriber keeps their previous plan meanwhile. */
    case PENDING = 'pending';
    case TRIALING = 'trialing';
    case ACTIVE = 'active';
    /** The period ended unpaid; usable until the grace period runs out. */
    case PAST_DUE = 'past_due';
    /** Cancelled by the subscriber; usable until ends_at. */
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';

    /**
     * Statuses that may still grant access (cancelled ones only until ends_at).
     *
     * @return list<self>
     */
    public static function live(): array
    {
        return [self::TRIALING, self::ACTIVE, self::PAST_DUE, self::CANCELLED];
    }
}
