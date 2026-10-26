<?php

namespace App\Models;

use App\Enums\DisputeReason;
use App\Enums\DisputeStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Not mass-assignable: written only by OpenDispute and ResolveDispute.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $order_id
 * @property int $opened_by
 * @property DisputeReason $reason
 * @property string $details
 * @property list<string>|null $photos
 * @property DisputeStatus $status
 * @property int|null $resolved_by
 * @property string|null $resolution_note
 * @property CarbonImmutable|null $resolved_at
 * @property CarbonImmutable|null $created_at
 * @property-read Order $order
 * @property-read User $opener
 * @property-read User|null $resolver
 */
class Dispute extends Model
{
    use Auditable, BelongsToTenant;

    /** Photos a buyer may add as proof; at least three are suggested. */
    public const MAX_PHOTOS = 6;

    public const SUGGESTED_PHOTOS = 3;

    /** Private: only the buyer, the seller and dispute staff see them. */
    public const PHOTO_DISK = 'local';

    protected function casts(): array
    {
        return [
            'reason' => DisputeReason::class,
            'status' => DisputeStatus::class,
            'resolved_at' => 'datetime',
            'photos' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isOpen(): bool
    {
        return $this->status === DisputeStatus::OPEN;
    }
}
