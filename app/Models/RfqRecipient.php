<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A supplier an RFQ was sent to.
 *
 * @property int $id
 * @property int $rfq_id
 * @property int $company_id
 * @property CarbonImmutable|null $notified_at
 * @property CarbonImmutable|null $viewed_at
 * @property CarbonImmutable|null $declined_at
 * @property string|null $decline_reason
 * @property-read Rfq $rfq
 * @property-read Company $company
 */
#[Fillable(['company_id', 'notified_at', 'viewed_at', 'declined_at', 'decline_reason'])]
class RfqRecipient extends Model
{
    protected function casts(): array
    {
        return [
            'notified_at' => 'datetime',
            'viewed_at' => 'datetime',
            'declined_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Rfq, $this>
     */
    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
