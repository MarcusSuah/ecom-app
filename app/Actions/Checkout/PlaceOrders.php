<?php

namespace App\Actions\Checkout;

use App\Actions\Cart\CartGroup;
use App\Actions\Cart\SummariseCart;
use App\Actions\Products\AlertLowStock;
use App\Enums\Fulfilment;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\ShopProduct;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlaceOrders
{
    public function __construct(private SummariseCart $summariseCart, private AlertLowStock $alertLowStock) {}

    /**
     * Turn the cart into one order per shop, all or nothing. Stock is
     * re-checked under row locks and decremented; prices, names and the
     * delivery address are copied onto the orders. Each order then waits
     * for its payment (InitiatePayment).
     *
     * @param  array{lat: float, lng: float, label: string, description: string|null, landmark: string|null}|null  $location
     * @return list<Order>
     */
    public function handle(User $buyer, Cart $cart, ?array $location): array
    {
        return DB::transaction(function () use ($buyer, $cart, $location): array {
            $productIds = $cart->items()->pluck('shop_product_id');

            if ($productIds->isEmpty()) {
                throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
            }

            // Lock the products first, so the stock read by the summary cannot change underneath us.
            ShopProduct::query()->whereKey($productIds)->orderBy('id')->lockForUpdate()->get();

            $groups = $this->summariseCart->handle($cart, $location);
            $problems = array_merge(...array_map(fn (CartGroup $group) => $group->problems, $groups));

            if ($problems !== []) {
                throw ValidationException::withMessages(['cart' => $problems]);
            }

            $orders = array_map(fn (CartGroup $group) => $this->placeOrder($buyer, $group, $location), $groups);

            $cart->items()->delete();
            $cart->update(['fulfilment_choices' => null]);

            return $orders;
        });
    }

    /**
     * @param  array{lat: float, lng: float, label: string, description: string|null, landmark: string|null}|null  $location
     */
    private function placeOrder(User $buyer, CartGroup $group, ?array $location): Order
    {
        $order = new Order;
        $order->forceFill([
            'number' => 'ORD-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
            'buyer_id' => $buyer->id,
            'seller_company_id' => $group->shop->company_id,
            'type' => OrderType::LOCAL,
            'fulfilment' => $group->fulfilment,
            'address_snapshot' => $group->fulfilment === Fulfilment::DELIVERY && $location ? [
                'label' => $location['label'],
                'lat' => $location['lat'],
                'lng' => $location['lng'],
                'description' => $location['description'],
                'landmark' => $location['landmark'],
                'distance_km' => $group->delivery->distanceKm ?? null,
            ] : null,
            'subtotal' => $group->subtotal,
            'delivery_fee' => $group->deliveryFee(),
            'total' => $group->total(),
            'currency' => $group->shop->currency,
            'status' => OrderStatus::PENDING_PAYMENT,
            'placed_at' => now(),
        ])->save();

        foreach ($group->items as $item) {
            $this->addItem($order, $item);
        }

        $order->history()->make()->forceFill([
            'from_status' => null,
            'to_status' => OrderStatus::PENDING_PAYMENT,
            'changed_by' => $buyer->id,
            'note' => 'Checked out',
            'created_at' => now(),
        ])->save();

        return $order;
    }

    private function addItem(Order $order, CartItem $item): void
    {
        $product = $item->product;

        $order->items()->make()->forceFill([
            'purchasable_type' => $product->getMorphClass(),
            'purchasable_id' => $product->id,
            'name' => $product->name,
            'unit_price' => $product->price,
            'quantity' => $item->quantity,
            'line_total' => $product->price * $item->quantity,
        ])->save();

        $before = $product->stockStatus();
        $product->decrement('stock', $item->quantity);
        $this->alertLowStock->handle($product, $before);
    }
}
