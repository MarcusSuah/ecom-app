<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSlug;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $parent_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $sort_order
 * @property-read Category|null $parent
 */
#[Fillable(['parent_id', 'name', 'description', 'sort_order'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasSlug;

    /**
     * The categories every new marketplace starts with.
     */
    public const DEFAULTS = [
        'Food & Beverages',
        'Agriculture',
        'Construction Materials',
        'Electronics',
        'Apparel & Textiles',
        'Home & Kitchen',
        'Health & Beauty',
        'Packaging',
        'Industrial Equipment',
        'Office & Stationery',
    ];

    public function slugSource(): string
    {
        return $this->name;
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name')->orderBy('id');
    }
}
