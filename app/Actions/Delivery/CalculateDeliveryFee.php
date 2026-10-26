<?php

namespace App\Actions\Delivery;

use App\Models\Shop;
use App\Support\Geo;

class CalculateDeliveryFee
{
    /**
     * Distance is the haversine distance times the road allowance. Within
     * the shop's radius the fee is its base fee plus the per-km rate for
     * every km after the first, and nothing at or above its free-delivery
     * threshold. Outside the radius (or for pickup-only shops) the location
     * is not deliverable.
     */
    public function handle(Shop $shop, float $lat, float $lng, int $subtotal): DeliveryQuote
    {
        $km = Geo::haversineKm((float) $shop->lat, (float) $shop->lng, $lat, $lng)
            * (float) config('marketplace.road_factor', 1.3);

        $deliverable = $shop->offers_delivery && $km <= (float) $shop->delivery_radius_km;
        $isFree = $shop->free_threshold !== null && $subtotal >= $shop->free_threshold;

        $standardFee = $shop->base_fee + (int) round(max(0, $km - 1) * $shop->per_km_rate);
        $fee = $isFree ? 0 : $standardFee;

        return new DeliveryQuote(
            distanceKm: round($km, 2),
            fee: $deliverable ? $fee : 0,
            isFree: $deliverable && $isFree,
            deliverable: $deliverable,
            currency: $shop->currency,
            standardFee: $deliverable ? $standardFee : 0,
        );
    }
}
