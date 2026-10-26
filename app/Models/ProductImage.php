<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Both files live on the public disk: a display copy (at most 1200px) and
 * a 400px square-cropped thumbnail.
 *
 * @property int $id
 * @property int $product_id
 * @property string $path
 * @property string $thumbnail_path
 * @property int $position
 */
#[Fillable(['path', 'thumbnail_path', 'position'])]
class ProductImage extends Model
{
    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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
