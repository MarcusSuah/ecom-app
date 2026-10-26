<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $quote_id
 * @property int $min_qty
 * @property int $unit_price
 */
#[Fillable(['min_qty', 'unit_price'])]
class QuoteTier extends Model
{
    protected function casts(): array
    {
        return [
            'min_qty' => 'integer',
            'unit_price' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }
}
