<?php

namespace App\Models;

use App\Enums\BillingInterval;
use App\Enums\SubscriptionStatus;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Price, currency and interval are copied from the plan when subscribing,
 * so later plan price changes do not touch existing subscribers.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $subscriber_type
 * @property int $subscriber_id
 * @property int $plan_id
 * @property SubscriptionStatus $status
 * @property int $price
 * @property string $currency
 * @property BillingInterval $interval
 * @property CarbonImmutable|null $trial_ends_at
 * @property CarbonImmutable|null $current_period_starts_at
 * @property CarbonImmutable|null $current_period_ends_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $ends_at
 * @property-read Plan $plan
 * @property-read Tenant|Company $subscriber
 */
#[Fillable(['tenant_id', 'subscriber_type', 'subscriber_id', 'plan_id', 'status', 'price', 'currency', 'interval', 'trial_ends_at', 'current_period_starts_at', 'current_period_ends_at', 'cancelled_at', 'ends_at'])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'interval' => BillingInterval::class,
            'price' => 'integer',
            'trial_ends_at' => 'datetime',
            'current_period_starts_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subscriber(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return HasMany<SubscriptionInvoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }

    public function grantsAccess(): bool
    {
        return match ($this->status) {
            SubscriptionStatus::TRIALING, SubscriptionStatus::ACTIVE, SubscriptionStatus::PAST_DUE => true,
            SubscriptionStatus::CANCELLED => $this->ends_at?->isFuture() ?? false,
            default => false,
        };
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, [SubscriptionStatus::TRIALING, SubscriptionStatus::ACTIVE, SubscriptionStatus::PAST_DUE], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'plan' => $this->plan->toSummary(),
            'trial_ends_at' => $this->trial_ends_at?->toISOString(),
            'current_period_ends_at' => $this->current_period_ends_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'is_cancellable' => $this->isCancellable(),
        ];
    }
}
