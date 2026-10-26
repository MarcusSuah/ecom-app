<?php

namespace App\Actions\Products;

use App\Models\PriceTier;

final readonly class QuantityPrice
{
    public function __construct(
        public PriceTier $tier,
        public int $quantity,
        public int $unitPrice,
        public int $total,
        public string $currency,
    ) {}
}
