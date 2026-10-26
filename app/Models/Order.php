<?php

namespace App\Models;

use App\Enums\DisputeStatus;
use App\Enums\Fulfilment;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Amounts, names and the delivery address are snapshots taken at checkout.
 * Nothing is mass-assignable: orders are only written by checkout and the
 * status actions.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $number
 * @property int $buyer_id
 * @property int $seller_company_id
 * @property OrderType $type
 * @property Fulfilment $fulfilment
 * @property PaymentMethod $payment_method
 * @property array{label: string, lat: float, lng: float, description: string|null, landmark: string|null, distance_km: float}|null $address_snapshot
 * @property int $subtotal
 * @property int $delivery_fee
 * @property int $total
 * @property string $currency
 * @property OrderStatus $status
 * @property int|null $quote_id
 * @property CarbonImmutable|null $placed_at
 * @property CarbonImmutable|null $delivered_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property-read User $buyer
 * @property-read Company $seller
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'type' => OrderType::class,
            'fulfilment' => Fulfilment::class,
            'payment_method' => PaymentMethod::class,
            'status' => OrderStatus::class,
            'address_snapshot' => 'array',
            'subtotal' => 'integer',
            'delivery_fee' => 'integer',
            'total' => 'integer',
            'placed_at' => 'datetime',
            'delivered_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * The payment currently holding or that held the money for this order.
     */
    public function securedPayment(): ?Payment
    {
        return $this->payments()
            ->whereIn('status', PaymentStatus::secured())
            ->latest('id')
            ->first();
    }

    /**
     * @return HasOne<Dispute, $this>
     */
    public function dispute(): HasOne
    {
        return $this->hasOne(Dispute::class);
    }

    /**
     * @return HasOne<Payout, $this>
     */
    public function payout(): HasOne
    {
        return $this->hasOne(Payout::class);
    }

    /**
     * @return HasMany<OrderStatusChange, $this>
     */
    public function history(): HasMany
    {
        return $this->hasMany(OrderStatusChange::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function hasOpenDispute(): bool
    {
        return $this->dispute()->where('status', DisputeStatus::OPEN)->exists();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'seller_company_id');
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * A pickup order the buyer pays in cash when collecting it.
     */
    public function paysCash(): bool
    {
        return $this->payment_method === PaymentMethod::CASH_ON_PICKUP;
    }

    /**
     * Cash on pickup is offered for local orders the buyer collects.
     */
    public function canPayCash(): bool
    {
        return $this->type === OrderType::LOCAL && $this->fulfilment === Fulfilment::PICKUP;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'type' => $this->type->value,
            'fulfilment' => $this->fulfilment->value,
            'payment_method' => $this->payment_method->value,
            'payment_method_label' => $this->payment_method->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'subtotal' => Money::format($this->subtotal, $this->currency),
            'delivery_fee' => Money::format($this->delivery_fee, $this->currency),
            'total' => Money::format($this->total, $this->currency),
            'address' => $this->address_snapshot,
            'placed_at' => $this->placed_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
