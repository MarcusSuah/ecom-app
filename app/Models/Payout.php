<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Not mass-assignable: written only by ReleasePayment and MarkPayoutPaid.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $company_id
 * @property int $order_id
 * @property int $gross
 * @property string $commission_rate
 * @property int $commission
 * @property int $net
 * @property string $currency
 * @property PayoutStatus $status
 * @property CarbonImmutable|null $paid_at
 * @property string|null $reference
 * @property CarbonImmutable|null $created_at
 * @property-read Company $company
 * @property-read Order $order
 */
class Payout extends Model
{
    use Auditable, BelongsToTenant;

    protected function casts(): array
    {
        return [
            'status' => PayoutStatus::class,
            'gross' => 'integer',
            'commission' => 'integer',
            'net' => 'integer',
            'commission_rate' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
