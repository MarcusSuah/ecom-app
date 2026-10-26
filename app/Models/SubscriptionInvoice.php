<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Database\Factories\SubscriptionInvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $subscription_id
 * @property string $number
 * @property int $amount
 * @property string $currency
 * @property InvoiceStatus $status
 * @property string|null $provider
 * @property string|null $provider_reference
 * @property CarbonImmutable $period_starts_at
 * @property CarbonImmutable $period_ends_at
 * @property CarbonImmutable|null $paid_at
 * @property array<string, mixed>|null $raw_payload
 * @property-read Subscription $subscription
 */
#[Fillable(['tenant_id', 'subscription_id', 'number', 'amount', 'currency', 'status', 'provider', 'provider_reference', 'period_starts_at', 'period_ends_at', 'paid_at', 'raw_payload'])]
#[Hidden(['raw_payload'])]
class SubscriptionInvoice extends Model
{
    /** @use HasFactory<SubscriptionInvoiceFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'amount' => 'integer',
            'period_starts_at' => 'datetime',
            'period_ends_at' => 'datetime',
            'paid_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function isPaid(): bool
    {
        return $this->status === InvoiceStatus::PAID;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'amount' => Money::format($this->amount, $this->currency),
            'status' => $this->status->value,
            'provider' => $this->provider,
            'period_starts_at' => $this->period_starts_at->toISOString(),
            'period_ends_at' => $this->period_ends_at->toISOString(),
            'paid_at' => $this->paid_at?->toISOString(),
        ];
    }
}
