<?php

use App\Actions\Products\PriceForQuantity;
use App\Actions\Products\QuantityBelowMinimum;
use App\Models\Product;

function tieredProduct(): Product
{
    return Product::factory()->state(['moq' => 100])->tiers([[100, 1000], [500, 900], [1000, 800]])->create();
}

it('uses the highest tier the quantity reaches', function (int $quantity, int $tierStart, int $unitPrice) {
    $price = app(PriceForQuantity::class)->handle(tieredProduct(), $quantity);

    expect($price->tier->min_qty)->toBe($tierStart)
        ->and($price->unitPrice)->toBe($unitPrice)
        ->and($price->total)->toBe($unitPrice * $quantity)
        ->and($price->currency)->toBe('RWF');
})->with([
    'exactly the MOQ' => [100, 100, 1000],
    'one below the second tier' => [499, 100, 1000],
    'exactly the second tier' => [500, 500, 900],
    'one below the top tier' => [999, 500, 900],
    'exactly the top tier' => [1000, 1000, 800],
    'far above the top tier' => [25000, 1000, 800],
]);

it('refuses quantities below the minimum order', function () {
    expect(fn () => app(PriceForQuantity::class)->handle(tieredProduct(), 99))
        ->toThrow(QuantityBelowMinimum::class, 'The minimum order quantity is 100; 99 was requested.');
});
