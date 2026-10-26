<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Enums\ProductUnit;
use App\Enums\StockStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSlug;
use App\Support\DisplayCurrency;
use Carbon\CarbonImmutable;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Scout\Searchable;

/**
 * A wholesale product listed by a supplier company.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $company_id
 * @property int $category_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property ProductUnit $unit
 * @property int $moq
 * @property int $lead_time_min_days
 * @property int $lead_time_max_days
 * @property string $currency
 * @property string $origin_country
 * @property bool $sample_available
 * @property int|null $sample_price
 * @property ProductStatus $status
 * @property int|null $min_unit_price
 * @property int|null $stock Units ready to ship; null when made to order
 * @property int $units_sold
 * @property CarbonImmutable|null $created_at
 * @property-read Company $company
 * @property-read Category $category
 * @property-read ProductImage|null $coverImage
 */
#[Fillable(['category_id', 'name', 'description', 'unit', 'moq', 'stock', 'lead_time_min_days', 'lead_time_max_days', 'currency', 'origin_country', 'sample_available', 'sample_price'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasSlug, Searchable;

    public const MAX_IMAGES = 10;

    protected function casts(): array
    {
        return [
            'unit' => ProductUnit::class,
            'status' => ProductStatus::class,
            'moq' => 'integer',
            'lead_time_min_days' => 'integer',
            'lead_time_max_days' => 'integer',
            'sample_available' => 'boolean',
            'sample_price' => 'integer',
            'min_unit_price' => 'integer',
            'stock' => 'integer',
            'units_sold' => 'integer',
        ];
    }

    /**
     * Out of stock once what is left cannot fill one minimum order; low
     * while it covers fewer than three.
     */
    public function stockStatus(): StockStatus
    {
        return match (true) {
            $this->stock === null => StockStatus::MADE_TO_ORDER,
            $this->stock < $this->moq => StockStatus::OUT_OF_STOCK,
            $this->stock < $this->moq * 3 => StockStatus::LOW_STOCK,
            default => StockStatus::IN_STOCK,
        };
    }

    /**
     * @return array{stock: int|null, stock_status: string, stock_label: string, units_sold: int}
     */
    public function stockBadge(): array
    {
        $status = $this->stockStatus();

        return [
            'stock' => $this->stock,
            'stock_status' => $status->value,
            'stock_label' => $status === StockStatus::LOW_STOCK ? number_format((int) $this->stock)." {$this->unit->value} left" : $status->label(),
            'units_sold' => $this->units_sold,
        ];
    }

    public function slugSource(): string
    {
        return $this->name;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<PriceTier, $this>
     */
    public function priceTiers(): HasMany
    {
        return $this->hasMany(PriceTier::class)->orderBy('min_qty');
    }

    /**
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasOne<ProductImage, $this>
     */
    public function coverImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->ofMany(['position' => 'min', 'id' => 'min']);
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), ProductStatus::ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === ProductStatus::ACTIVE;
    }

    /**
     * Whether searches run as SQL on this table (Scout "database" driver)
     * rather than against an external index such as Meilisearch.
     */
    public static function searchesDatabaseDirectly(): bool
    {
        return in_array(config('scout.driver'), ['database', 'collection'], true);
    }

    /**
     * The database engine runs a LIKE over every key returned here, so it
     * only gets the text columns. External engines get the full document;
     * every filterable field shares its name with a real column so the same
     * Scout where() clauses work on both, except supplier_verified (see
     * SearchProducts).
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        if (static::searchesDatabaseDirectly()) {
            return [
                'name' => $this->name,
                'description' => $this->description,
            ];
        }

        $this->loadMissing(['company.latestVerification', 'category']);

        return [
            'id' => (int) $this->id,
            'tenant_id' => (int) $this->tenant_id,
            'company_id' => (int) $this->company_id,
            'category_id' => (int) $this->category_id,
            'name' => $this->name,
            'description' => (string) $this->description,
            'category_name' => $this->category->name,
            'supplier_name' => $this->company->name,
            'supplier_verified' => $this->company->isVerified(),
            'status' => $this->status->value,
            'moq' => (int) $this->moq,
            'min_unit_price' => (int) $this->min_unit_price,
            'currency' => $this->currency,
            'created_at' => (int) $this->created_at?->getTimestamp(),
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function makeAllSearchableUsing(Builder $query): Builder
    {
        return $query->with(['company.latestVerification', 'category']);
    }

    public function leadTimeLabel(): string
    {
        return $this->lead_time_min_days === $this->lead_time_max_days
            ? "{$this->lead_time_min_days} days"
            : "{$this->lead_time_min_days}–{$this->lead_time_max_days} days";
    }

    /**
     * The card shown in catalogue grids. Expects company.latestVerification,
     * category and coverImage to be loaded.
     *
     * @return array<string, mixed>
     */
    public function toCard(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'unit' => $this->unit->value,
            'unit_label' => $this->unit->label(),
            'moq' => $this->moq,
            'currency' => $this->currency,
            'from_price' => $this->min_unit_price !== null ? app(DisplayCurrency::class)->format($this->min_unit_price, $this->currency) : null,
            'from_price_listed' => $this->min_unit_price !== null ? app(DisplayCurrency::class)->listed($this->min_unit_price, $this->currency) : null,
            'from_price_minor' => $this->min_unit_price,
            ...$this->stockBadge(),
            'lead_time' => $this->leadTimeLabel(),
            'thumbnail_url' => $this->coverImage?->thumbnailUrl(),
            'category' => ['name' => $this->category->name, 'slug' => $this->category->slug],
            'supplier' => [
                'name' => $this->company->name,
                'slug' => $this->company->slug,
                'is_verified' => $this->company->isVerified(),
                'city' => $this->company->city,
                'country' => $this->company->country,
            ],
        ];
    }
}
