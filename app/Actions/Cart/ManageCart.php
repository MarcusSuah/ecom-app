<?php

namespace App\Actions\Cart;

use App\Enums\Fulfilment;
use App\Models\Cart;
use App\Models\Shop;
use App\Models\ShopProduct;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * The member's cart on the current marketplace. Stock is only advisory
 * here; checkout checks it for real.
 */
class ManageCart
{
    public const MAX_QUANTITY = 999;

    public function cartFor(User $user): Cart
    {
        $cart = Cart::query()->where('user_id', $user->id)->first()
            ?? tap(new Cart, function (Cart $cart) use ($user): void {
                $cart->user()->associate($user);
                $cart->save();
            });

        return $this->expireIfIdle($cart);
    }

    /**
     * A cart left Cart::IDLE_MINUTES without a change is emptied, so items
     * nobody is buying do not linger (the page then shows 0).
     */
    public function expireIfIdle(Cart $cart): Cart
    {
        $expiresAt = $cart->expiresAt();

        if ($expiresAt !== null && $expiresAt->isPast() && $cart->items()->exists()) {
            $cart->items()->delete();
            $cart->forceFill(['fulfilment_choices' => null])->save();
        }

        return $cart;
    }

    public function add(User $user, ShopProduct $product, int $quantity): Cart
    {
        if (! $product->is_active || ! $product->shop->company->isListable()) {
            throw ValidationException::withMessages(['quantity' => 'This item is no longer available.']);
        }

        if ($product->shop->company->owner_id === $user->id) {
            throw ValidationException::withMessages(['quantity' => 'You cannot order from your own shop.']);
        }

        $cart = $this->cartFor($user);
        $item = $cart->items()->firstOrNew(['shop_product_id' => $product->id], ['quantity' => 0]);
        $item->quantity = min(self::MAX_QUANTITY, $item->quantity + $quantity);

        if ($item->quantity > $product->stock) {
            throw ValidationException::withMessages(['quantity' => "Only {$product->stock} left in stock."]);
        }

        $item->save();

        return $cart;
    }

    public function update(User $user, ShopProduct $product, int $quantity): Cart
    {
        $cart = $this->cartFor($user);

        if ($quantity <= 0) {
            $cart->items()->where('shop_product_id', $product->id)->delete();
            $cart->touch();

            return $cart;
        }

        if ($quantity > $product->stock) {
            throw ValidationException::withMessages(['quantity' => "Only {$product->stock} left in stock."]);
        }

        $cart->items()->where('shop_product_id', $product->id)->update(['quantity' => min(self::MAX_QUANTITY, $quantity)]);
        // A change keeps the cart alive for another Cart::IDLE_MINUTES.
        $cart->touch();

        return $cart;
    }

    public function chooseFulfilment(User $user, Shop $shop, Fulfilment $fulfilment): Cart
    {
        $offered = $fulfilment === Fulfilment::DELIVERY ? $shop->offers_delivery : $shop->offers_pickup;

        if (! $offered) {
            throw ValidationException::withMessages(['fulfilment' => "{$shop->company->name} does not offer {$fulfilment->value}."]);
        }

        $cart = $this->cartFor($user);
        $choices = $cart->fulfilment_choices ?? [];
        $choices[$shop->id] = $fulfilment->value;
        $cart->fulfilment_choices = $choices;
        $cart->save();

        return $cart;
    }
}
