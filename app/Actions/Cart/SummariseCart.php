<?php

namespace App\Actions\Cart;

use App\Actions\Delivery\CalculateDeliveryFee;
use App\Enums\Fulfilment;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Shop;
use App\Support\DisplayCurrency;

/**
 * The cart grouped by shop, with each shop's fulfilment, live delivery fee
 * and anything that would stop checkout. Checkout uses the same groups, so
 * the customer pays exactly what this shows.
 */
class SummariseCart
{
    public function __construct(private CalculateDeliveryFee $calculateDeliveryFee) {}

    /**
     * @param  array{lat: float, lng: float}|null  $location
     * @return list<CartGroup>
     */
    public function handle(Cart $cart, ?array $location): array
    {
        $items = $cart->items()->with(['product.shop.company'])->get();

        return array_values($items
            ->groupBy(fn (CartItem $item) => $item->product->shop_id)
            ->map(function ($shopItems) use ($cart, $location): CartGroup {
                /** @var Shop $shop */
                $shop = $shopItems->first()->product->shop;
                $fulfilment = $cart->fulfilmentFor($shop);
                $subtotal = (int) $shopItems->sum(fn (CartItem $item) => $item->product->price * $item->quantity);
                $problems = [];

                foreach ($shopItems as $item) {
                    if (! $item->product->is_active) {
                        $problems[] = "{$item->product->name} is no longer available.";
                    } elseif ($item->quantity > $item->product->stock) {
                        $problems[] = "Only {$item->product->stock} {$item->product->name} left in stock.";
                    }
                }

                $quote = null;
                if ($fulfilment === Fulfilment::DELIVERY) {
                    if ($location === null) {
                        $problems[] = 'Set your delivery location to see the delivery fee.';
                    } else {
                        $quote = $this->calculateDeliveryFee->handle($shop, $location['lat'], $location['lng'], $subtotal);

                        if (! $quote->deliverable) {
                            $problems[] = "{$shop->company->name} does not deliver to your location (".number_format($quote->distanceKm, 1).' km away). Choose pickup or another address.';
                        }
                    }
                }

                return new CartGroup($shop, array_values($shopItems->all()), $fulfilment, $subtotal, $quote, $problems);
            })
            ->all());
    }

    /**
     * @param  list<CartGroup>  $groups
     * @return list<array<string, mixed>>
     */
    public static function present(array $groups): array
    {
        $display = app(DisplayCurrency::class);

        return array_map(fn (CartGroup $group) => [
            'shop' => [
                'id' => $group->shop->id,
                'name' => $group->shop->company->name,
                'slug' => $group->shop->company->slug,
                'offers_pickup' => $group->shop->offers_pickup,
                'offers_delivery' => $group->shop->offers_delivery,
                'address' => $group->shop->address_text,
                'free_threshold' => $group->shop->free_threshold !== null ? $display->format($group->shop->free_threshold, $group->shop->currency) : null,
                'free_threshold_minor' => $group->shop->free_threshold,
                'currency' => $group->shop->currency,
            ],
            'items' => array_map(fn (CartItem $item) => [
                'product_id' => $item->product->id,
                'name' => $item->product->name,
                'image_url' => $item->product->imageUrl(),
                'unit_price' => $display->format($item->product->price, $group->shop->currency),
                'unit_price_minor' => $item->product->price,
                'quantity' => $item->quantity,
                'stock' => $item->product->stock,
                'line_total' => $display->format($item->product->price * $item->quantity, $group->shop->currency),
            ], $group->items),
            'fulfilment' => $group->fulfilment->value,
            'subtotal' => $display->format($group->subtotal, $group->shop->currency),
            'delivery' => $group->delivery?->toArray(),
            'total' => $display->format($group->total(), $group->shop->currency),
            // What the shop charges, when the visitor sees prices in another currency.
            'total_listed' => $display->listed($group->total(), $group->shop->currency),
            'problems' => $group->problems,
        ], $groups);
    }
}
