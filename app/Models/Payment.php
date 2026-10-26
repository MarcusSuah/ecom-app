<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Not mass-assignable: written only by the payment actions.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $order_id
 * @property string $reference
 * @property string $provider
 * @property string|null $provider_reference
 * @property int $amount
 * @property string $currency
 * @property PaymentStatus $status
 * @property string|null $payer_phone
 * @property CarbonImmutable|null $held_at
 * @property CarbonImmutable|null $released_at
 * @property CarbonImmutable|null $refunded_at
 * @property array<string, mixed>|null $raw_payload
 * @property-read Order $order
 */
#[Hidden(['raw_payload'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'integer',
            'held_at' => 'datetime',
            'released_at' => 'datetime',
            'refunded_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isHeld(): bool
    {
        return $this->status === PaymentStatus::HELD;
    }
}
