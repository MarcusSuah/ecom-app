<?php

namespace App\Models;

use App\Enums\BillingInterval;
use App\Enums\PlanAudience;
use App\Enums\PlanLimit;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasSlug;
use App\Support\Money;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Not tenant-scoped on purpose: marketplace plans have no tenant. Always
 * query through forMarketplaces() or forSellersOf().
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property PlanAudience $audience
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $price
 * @property string $currency
 * @property BillingInterval $interval
 * @property int $trial_days
 * @property array<string, int|bool|null>|null $limits
 * @property bool $is_default
 * @property bool $is_active
 * @property int $sort_order
 */
#[Fillable(['tenant_id', 'audience', 'name', 'slug', 'description', 'price', 'currency', 'interval', 'trial_days', 'limits', 'is_default', 'is_active', 'sort_order'])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use Auditable, HasFactory, HasSlug;

    protected function casts(): array
    {
        return [
            'audience' => PlanAudience::class,
            'interval' => BillingInterval::class,
            'price' => 'integer',
            'trial_days' => 'integer',
            'limits' => 'array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function slugSource(): string
    {
        return $this->name;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSlugUniqueness(Builder $query): Builder
    {
        return $query->where('audience', $this->audience)
            ->where('tenant_id', $this->tenant_id);
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function forMarketplaces(Builder $query): void
    {
        $query->where('audience', PlanAudience::MARKETPLACE)->whereNull('tenant_id');
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function forSellersOf(Builder $query, Tenant|int $tenant): void
    {
        $query->where('audience', PlanAudience::SELLER)
            ->where('tenant_id', $tenant instanceof Tenant ? $tenant->id : $tenant);
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('price')->orderBy('id');
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function isFree(): bool
    {
        return $this->price === 0;
    }

    public function limit(PlanLimit $limit): int|bool|null
    {
        $limits = $this->limits ?? [];

        if (! array_key_exists($limit->value, $limits)) {
            return $limit->defaultValue();
        }

        $value = $limits[$limit->value];

        if ($limit->isSwitch()) {
            return (bool) $value;
        }

        return $value === null ? null : (int) $value;
    }

    public function formattedPrice(): string
    {
        return $this->isFree() ? 'Free' : Money::format($this->price, $this->currency).' / '.$this->interval->value;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => $this->price,
            'currency' => $this->currency,
            'interval' => $this->interval->value,
            'formatted_price' => $this->formattedPrice(),
            'trial_days' => $this->trial_days,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'limits' => collect($this->audience->limits())->map(fn (PlanLimit $limit) => [
                'key' => $limit->value,
                'label' => $limit->label(),
                'is_switch' => $limit->isSwitch(),
                'value' => $this->limit($limit),
            ])->values()->all(),
        ];
    }
}
