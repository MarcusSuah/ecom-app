<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $cart_id
 * @property int $shop_product_id
 * @property int $quantity
 * @property-read ShopProduct $product
 */
#[Fillable(['shop_product_id', 'quantity'])]
class CartItem extends Model
{
    /** Every change to an item counts as activity on the cart (Cart::IDLE_MINUTES). */
    protected $touches = ['cart'];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    /**
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * @return BelongsTo<ShopProduct, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(ShopProduct::class, 'shop_product_id');
    }
}
