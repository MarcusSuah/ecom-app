<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $product_id
 * @property int $min_qty
 * @property int $unit_price
 */
#[Fillable(['min_qty', 'unit_price'])]
class PriceTier extends Model
{
    protected function casts(): array
    {
        return [
            'min_qty' => 'integer',
            'unit_price' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
