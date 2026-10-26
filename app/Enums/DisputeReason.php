<?php

namespace App\Enums;

enum DisputeReason: string
{
    case NOT_RECEIVED = 'not_received';
    case NOT_AS_DESCRIBED = 'not_as_described';
    case DAMAGED = 'damaged';
    case INCOMPLETE = 'incomplete';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::NOT_RECEIVED => 'I did not receive the order',
            self::NOT_AS_DESCRIBED => 'Not as described',
            self::DAMAGED => 'Arrived damaged',
            self::INCOMPLETE => 'Items are missing',
            self::OTHER => 'Something else',
        };
    }
}
