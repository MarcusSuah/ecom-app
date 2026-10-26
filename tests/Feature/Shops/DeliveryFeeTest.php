<?php

use App\Actions\Delivery\CalculateDeliveryFee;
use App\Actions\Delivery\DeliveryQuote;
use App\Models\Shop;
use App\Support\Geo;

/**
 * Kilometres per degree of latitude on the haversine sphere.
 */
const KM_PER_DEGREE = 6371 * M_PI / 180;

/**
 * A point $km straight north of the shop.
 */
function quoteAt(Shop $shop, float $km, int $subtotal = 0): DeliveryQuote
{
    return app(CalculateDeliveryFee::class)->handle($shop, (float) $shop->lat + $km / KM_PER_DEGREE, (float) $shop->lng, $subtotal);
}

beforeEach(function () {
    // Straight-line distances make the fee rules easy to read; one test covers the road factor.
    config(['marketplace.road_factor' => 1.0]);
});

it('charges only the base fee within the first km', function (float $km) {
    $shop = Shop::factory()->create(['base_fee' => 1000, 'per_km_rate' => 300]);

    $quote = quoteAt($shop, $km);

    expect($quote->fee)->toBe(1000)->and($quote->deliverable)->toBeTrue()->and($quote->isFree)->toBeFalse();
})->with(['at the shop' => [0.0], 'half a km' => [0.5], 'exactly 1 km' => [1.0]]);

it('adds the per-km rate for every km after the first', function (float $km, int $fee) {
    $shop = Shop::factory()->create(['base_fee' => 1000, 'per_km_rate' => 300, 'delivery_radius_km' => 10]);

    expect(quoteAt($shop, $km)->fee)->toBe($fee);
})->with([
    '2 km' => [2.0, 1300],
    '3.5 km' => [3.5, 1750],
    '5 km' => [5.0, 2200],
]);

it('delivers free at and above the shop\'s threshold', function (int $subtotal, bool $free, int $fee) {
    $shop = Shop::factory()->create(['base_fee' => 1000, 'per_km_rate' => 300, 'free_threshold' => 20000]);

    $quote = quoteAt($shop, 3.0, $subtotal);

    expect($quote->isFree)->toBe($free)->and($quote->fee)->toBe($fee);
})->with([
    'just below' => [19999, false, 1600],
    'exactly at' => [20000, true, 0],
    'above' => [35000, true, 0],
]);

it('never delivers free when the shop has no threshold', function () {
    $shop = Shop::factory()->create(['free_threshold' => null]);

    expect(quoteAt($shop, 0.5, 1_000_000)->isFree)->toBeFalse();
});

it('does not deliver outside the radius or for pickup-only shops', function () {
    $shop = Shop::factory()->create(['delivery_radius_km' => 5]);

    expect(quoteAt($shop, 4.9)->deliverable)->toBeTrue()
        ->and(quoteAt($shop, 5.1)->deliverable)->toBeFalse()
        ->and(quoteAt($shop, 5.1)->fee)->toBe(0)
        ->and(quoteAt(Shop::factory()->pickupOnly()->create(), 0.2)->deliverable)->toBeFalse();
});

it('adds the road allowance to the straight-line distance', function () {
    config(['marketplace.road_factor' => 1.3]);
    $shop = Shop::factory()->create(['base_fee' => 1000, 'per_km_rate' => 300, 'delivery_radius_km' => 10]);

    // 3 km straight line × 1.3 = 3.9 km by road: 1,000 + 2.9 × 300.
    $quote = quoteAt($shop, 3.0);

    expect($quote->distanceKm)->toBe(3.9)->and($quote->fee)->toBe(1870);
});

it('measures great-circle distance', function () {
    // Kigali city centre to Kimironko is roughly 7.1 km in a straight line.
    expect(Geo::haversineKm(-1.9441, 30.0619, -1.9496, 30.1260))->toBeGreaterThan(7.0)->toBeLessThan(7.3);
});
