<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes a model to the current marketplace. Queries only see rows of the
 * current tenant, and new rows are stamped with it. With no tenant active
 * (console, platform screens) the scope does nothing, so code there must
 * filter by tenant explicitly.
 *
 * @property int $tenant_id
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query): void {
            $tenantId = app(CurrentTenant::class)->id();

            if ($tenantId !== null) {
                $query->where($query->getModel()->qualifyColumn('tenant_id'), $tenantId);
            }
        });

        static::creating(function (self $model): void {
            if (! $model->getAttribute('tenant_id')) {
                $model->setAttribute('tenant_id', app(CurrentTenant::class)->require()->id);
            }
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Slugs only need to be unique inside one marketplace (see HasSlug).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSlugUniqueness(Builder $query): Builder
    {
        return $query->where('tenant_id', $this->getAttribute('tenant_id') ?? app(CurrentTenant::class)->id());
    }
}
