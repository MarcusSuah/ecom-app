<?php

namespace App\Enums;

/**
 * What the stock badge on a product or shop item says.
 */
enum StockStatus: string
{
    case IN_STOCK = 'in_stock';
    case LOW_STOCK = 'low_stock';
    case OUT_OF_STOCK = 'out_of_stock';
    /** Wholesale listings without a stock count are produced on order. */
    case MADE_TO_ORDER = 'made_to_order';

    public function label(): string
    {
        return match ($this) {
            self::IN_STOCK => 'In stock',
            self::LOW_STOCK => 'Low stock',
            self::OUT_OF_STOCK => 'Out of stock',
            self::MADE_TO_ORDER => 'Made to order',
        };
    }

    public function canOrder(): bool
    {
        return $this !== self::OUT_OF_STOCK;
    }
}
