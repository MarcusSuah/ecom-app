<?php

namespace App\Models;

use App\Enums\StockStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ShopProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * A single item a local shop sells. Price is in the shop's currency.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $shop_id
 * @property int $category_id
 * @property string $name
 * @property string|null $description
 * @property int $price
 * @property int $stock
 * @property int $units_sold
 * @property string|null $image Thumbnail of the featured photo, kept in step with the gallery
 * @property bool $is_active
 * @property-read Shop $shop
 * @property-read Category $category
 * @property-read Collection<int, ShopProductImage> $images
 */
#[Fillable(['category_id', 'name', 'description', 'price', 'stock', 'is_active'])]
class ShopProduct extends Model
{
    /** @use HasFactory<ShopProductFactory> */
    use Auditable, BelongsToTenant, HasFactory;

    public const MAX_IMAGES = 10;

    /** At or below this many left, the badge says "low stock". */
    public const LOW_STOCK = 5;

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'stock' => 'integer',
            'units_sold' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The featured photo first.
     *
     * @return HasMany<ShopProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ShopProductImage::class)->orderBy('position')->orderBy('id');
    }

    public function stockStatus(): StockStatus
    {
        return match (true) {
            $this->stock <= 0 => StockStatus::OUT_OF_STOCK,
            $this->stock <= self::LOW_STOCK => StockStatus::LOW_STOCK,
            default => StockStatus::IN_STOCK,
        };
    }

    /**
     * Badge data for the storefront and the shop's own list.
     *
     * @return array{stock_status: string, stock_label: string, units_sold: int}
     */
    public function stockBadge(): array
    {
        $status = $this->stockStatus();

        return [
            'stock_status' => $status->value,
            'stock_label' => $status === StockStatus::LOW_STOCK ? "Only {$this->stock} left" : $status->label(),
            'units_sold' => $this->units_sold,
        ];
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function available(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function imageUrl(): ?string
    {
        return $this->image ? Storage::disk('public')->url($this->image) : null;
    }
}
