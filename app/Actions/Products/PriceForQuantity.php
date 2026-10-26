<?php

namespace App\Actions\Products;

use App\Models\PriceTier;
use App\Models\Product;

class PriceForQuantity
{
    /**
     * The tier that applies to a quantity is the one with the highest
     * starting quantity at or below it.
     *
     * @throws QuantityBelowMinimum
     */
    public function handle(Product $product, int $quantity): QuantityPrice
    {
        if ($quantity < $product->moq) {
            throw new QuantityBelowMinimum($product->moq, $quantity);
        }

        /** @var PriceTier|null $tier */
        $tier = $product->priceTiers
            ->sortByDesc('min_qty')
            ->first(fn (PriceTier $tier) => $tier->min_qty <= $quantity);

        if ($tier === null) {
            throw new QuantityBelowMinimum($product->moq, $quantity);
        }

        return new QuantityPrice(
            tier: $tier,
            quantity: $quantity,
            unitPrice: $tier->unit_price,
            total: $tier->unit_price * $quantity,
            currency: $product->currency,
        );
    }
}
