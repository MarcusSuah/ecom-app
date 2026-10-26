<?php

namespace App\Actions\Shops;

use App\Actions\Delivery\CalculateDeliveryFee;
use App\Models\Shop;
use App\Support\Money;

class FindShops
{
    public function __construct(private CalculateDeliveryFee $calculateDeliveryFee) {}

    /**
     * Every shop on the marketplace with its distance and delivery estimate
     * from the customer's location, nearest first. Without a location the
     * shops are listed by name and nothing can be filtered by area.
     *
     * Sorting happens in PHP: a marketplace has tens of shops, not thousands.
     *
     * @param  array{lat: float, lng: float}|null  $location
     * @return list<array<string, mixed>>
     */
    public function handle(?array $location, bool $deliversToMyAreaOnly = false): array
    {
        $now = now();

        $shops = Shop::query()
            ->whereHas('company', fn ($query) => $query->listable())
            ->with(['company.latestVerification'])
            ->withCount(['products as available_products_count' => fn ($query) => $query->where('is_active', true)])
            ->get()
            ->map(function (Shop $shop) use ($location, $now) {
                // An empty basket shows the fee a typical small order would pay.
                $quote = $location ? $this->calculateDeliveryFee->handle($shop, $location['lat'], $location['lng'], 0) : null;

                return [
                    'shop' => $shop,
                    'card' => [
                        'id' => $shop->id,
                        'name' => $shop->company->name,
                        'slug' => $shop->company->slug,
                        'logo_url' => $shop->company->logoUrl(),
                        'is_verified' => $shop->company->isVerified(),
                        'address' => $shop->address_text,
                        'landmark' => $shop->landmark,
                        'offers_pickup' => $shop->offers_pickup,
                        'offers_delivery' => $shop->offers_delivery,
                        'is_open' => $shop->isOpenAt($now),
                        'free_threshold' => $shop->free_threshold !== null ? Money::format($shop->free_threshold, $shop->currency) : null,
                        'base_fee' => Money::format($shop->base_fee, $shop->currency),
                        'per_km_rate' => Money::format($shop->per_km_rate, $shop->currency),
                        'delivery_radius_km' => (float) $shop->delivery_radius_km,
                        'products_count' => (int) $shop->getAttribute('available_products_count'),
                        'delivery' => $quote?->toArray(),
                    ],
                ];
            });

        if ($location && $deliversToMyAreaOnly) {
            $shops = $shops->filter(fn (array $row) => $row['card']['delivery']['deliverable']);
        }

        $sorted = $location
            ? $shops->sortBy(fn (array $row) => $row['card']['delivery']['distance_km'])
            : $shops->sortBy(fn (array $row) => $row['card']['name']);

        return array_values($sorted->map(fn (array $row) => $row['card'])->all());
    }
}
