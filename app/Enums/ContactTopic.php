<?php

namespace App\Enums;

enum ContactTopic: string
{
    case BUYING = 'buying';
    case SELLING = 'selling';
    case PAYMENTS = 'payments';
    case SHIPPING = 'shipping';
    case ACCOUNT = 'account';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::BUYING => 'Buying or an order',
            self::SELLING => 'Selling on the marketplace',
            self::PAYMENTS => 'Payments and refunds',
            self::SHIPPING => 'Shipping and customs',
            self::ACCOUNT => 'My account',
            self::OTHER => 'Something else',
        };
    }
}
