<?php

namespace App\Actions\Delivery;

use App\Support\DisplayCurrency;

final readonly class DeliveryQuote
{
    public function __construct(
        public float $distanceKm,
        public int $fee,
        public bool $isFree,
        public bool $deliverable,
        public string $currency,
        /** The distance fee before any free-delivery threshold, so pages can recompute it live. */
        public int $standardFee = 0,
    ) {}

    /**
     * @return array{distance_km: float, fee: int, standard_fee: int, formatted_fee: string, is_free: bool, deliverable: bool}
     */
    public function toArray(): array
    {
        return [
            'distance_km' => $this->distanceKm,
            'fee' => $this->fee,
            'standard_fee' => $this->standardFee,
            'formatted_fee' => $this->isFree ? 'Free' : app(DisplayCurrency::class)->format($this->fee, $this->currency),
            'is_free' => $this->isFree,
            'deliverable' => $this->deliverable,
        ];
    }
}
