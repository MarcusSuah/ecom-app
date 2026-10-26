<?php

namespace App\Models;

use App\Enums\CompanyType;
use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSlug;
use App\Models\Concerns\HasSubscriptions;
use Carbon\CarbonImmutable;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Storage;

/**
 * A supplier or shop on one marketplace.
 *
 * A company is verified only while its latest verification is approved.
 * There is deliberately no column for it: nothing but a verifier's
 * decision on the latest application can make a company verified.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $owner_id
 * @property CompanyType $type
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $country
 * @property string|null $city
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $logo
 * @property bool $is_featured
 * @property CarbonImmutable|null $suspended_at
 * @property string|null $suspended_reason
 * @property CarbonImmutable|null $created_at
 * @property-read User $owner
 * @property-read Verification|null $latestVerification
 * @property-read int|null $listed_products_count
 */
#[Fillable(['name', 'description', 'country', 'city', 'phone', 'email'])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasSlug, HasSubscriptions;

    protected function casts(): array
    {
        return [
            'type' => CompanyType::class,
            'is_featured' => 'boolean',
            'suspended_at' => 'immutable_datetime',
        ];
    }

    public function slugSource(): string
    {
        return $this->name;
    }

    /**
     * Public supplier pages use the slug: /suppliers/{slug}.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<Verification, $this>
     */
    public function verifications(): HasMany
    {
        return $this->hasMany(Verification::class);
    }

    /**
     * @return HasOne<Verification, $this>
     */
    public function latestVerification(): HasOne
    {
        return $this->hasOne(Verification::class)->latestOfMany();
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Re-send this company's products to the search index, whose documents
     * include the supplier's name and verification. Nothing to do when
     * searches run on the database directly.
     */
    public function syncProductsToSearch(): void
    {
        if (Product::searchesDatabaseDirectly()) {
            return;
        }

        foreach ($this->products()->with(['company.latestVerification', 'category'])->lazyById() as $product) {
            $product->searchable();
        }
    }

    /**
     * Delivery settings, for shop companies.
     *
     * @return HasOne<Shop, $this>
     */
    public function shop(): HasOne
    {
        return $this->hasOne(Shop::class);
    }

    public function isShop(): bool
    {
        return $this->type === CompanyType::SHOP;
    }

    public function isSupplier(): bool
    {
        return $this->type === CompanyType::SUPPLIER;
    }

    /**
     * Companies whose latest verification is approved.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function verified(Builder $query): void
    {
        $query->whereExists(fn (QueryBuilder $sub) => $sub
            ->from('verifications as latest_verification')
            ->whereColumn('latest_verification.company_id', 'companies.id')
            ->where('latest_verification.status', VerificationStatus::APPROVED->value)
            ->whereRaw('latest_verification.id = (select max(v.id) from verifications as v where v.company_id = companies.id)'));
    }

    /**
     * Businesses the storefront shows: not suspended by an admin, and run by
     * an owner whose account is active, not blocked and not waiting for
     * (or refused) approval.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function listable(Builder $query): void
    {
        $query->whereNull('companies.suspended_at')
            ->whereHas('owner', fn (Builder $owner) => $owner
                ->where('is_active', true)
                ->whereNull('blocked_at')
                ->whereNotIn('status', [UserStatus::PENDING->value, UserStatus::REJECTED->value]));
    }

    public function isListable(): bool
    {
        return $this->suspended_at === null
            && $this->owner->is_active
            && $this->owner->blocked_at === null
            && ! $this->owner->isPending()
            && ! $this->owner->isRejected();
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function ofType(Builder $query, CompanyType $type): void
    {
        $query->where('type', $type);
    }

    public function isVerified(): bool
    {
        return $this->latestVerification?->status === VerificationStatus::APPROVED;
    }

    public function hasPendingVerification(): bool
    {
        return $this->latestVerification?->status === VerificationStatus::PENDING;
    }

    /**
     * A new application is allowed when there is none yet or the latest was rejected.
     */
    public function canSubmitVerification(): bool
    {
        return $this->latestVerification === null
            || $this->latestVerification->status === VerificationStatus::REJECTED;
    }

    public function billingTenantId(): int
    {
        return $this->tenant_id;
    }

    /**
     * Companies without a subscription fall back to their marketplace's default seller plan.
     */
    public function fallbackPlan(): ?Plan
    {
        return Plan::query()->forSellersOf($this->tenant_id)
            ->where('is_default', true)
            ->where('is_active', true)
            ->ordered()
            ->first();
    }

    public function logoUrl(): ?string
    {
        return $this->logo ? Storage::disk('public')->url($this->logo) : null;
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
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'city' => $this->city,
            'country' => $this->country,
            'logo_url' => $this->logoUrl(),
            'is_featured' => $this->is_featured,
            'is_verified' => $this->relationLoaded('latestVerification') ? $this->isVerified() : null,
            'verification_status' => $this->relationLoaded('latestVerification') ? $this->latestVerification?->status->value : null,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
