<?php

namespace App\Models;

use App\Enums\TenantStatus;
use App\Models\Concerns\HasSubscriptions;
use App\Support\CurrentTenant;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A marketplace run on the platform. Every marketplace has its own
 * companies, categories, seller plans and staff roles, and pays the
 * platform through a "marketplace" plan subscription.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $domain
 * @property TenantStatus $status
 * @property string $default_currency
 * @property string|null $support_email
 */
#[Fillable(['name', 'slug', 'domain', 'status', 'default_currency', 'support_email'])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, HasSubscriptions;

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
        ];
    }

    public static function current(): ?self
    {
        return app(CurrentTenant::class)->get();
    }

    public function makeCurrent(): static
    {
        app(CurrentTenant::class)->set($this);

        return $this;
    }

    /**
     * @return HasMany<Company, $this>
     */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    /**
     * Seller plans this marketplace offers to its companies.
     *
     * @return HasMany<Plan, $this>
     */
    public function sellerPlans(): HasMany
    {
        return $this->hasMany(Plan::class)->where('audience', 'seller');
    }

    public function isSuspended(): bool
    {
        return $this->status === TenantStatus::SUSPENDED;
    }

    /**
     * The platform's own marketplace (the fallback tenant): always open and
     * never billed for a platform plan.
     */
    public function isSystem(): bool
    {
        return $this->slug === config('tenancy.fallback_tenant');
    }

    /**
     * The tenant's own subscriptions are recorded against itself.
     */
    public function billingTenantId(): int
    {
        return $this->id;
    }
}
