<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A photo of a shop item, like ProductImage: a display copy (at most
 * 1200px) and a 400px square thumbnail on the public disk. Position 0 is
 * the featured photo.
 *
 * @property int $id
 * @property int $shop_product_id
 * @property string $path
 * @property string $thumbnail_path
 * @property int $position
 */
#[Fillable(['path', 'thumbnail_path', 'position'])]
class ShopProductImage extends Model
{
    /**
     * @return BelongsTo<ShopProduct, $this>
     */
    public function shopProduct(): BelongsTo
    {
        return $this->belongsTo(ShopProduct::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    public function thumbnailUrl(): string
    {
        return Storage::disk('public')->url($this->thumbnail_path);
    }
}
