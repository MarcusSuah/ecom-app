<?php

namespace App\Models;

use App\Enums\QuoteStatus;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A supplier's reply to an RFQ.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $rfq_id
 * @property int $company_id
 * @property string $currency
 * @property int $lead_time_min_days
 * @property int $lead_time_max_days
 * @property string|null $sample_terms
 * @property CarbonImmutable $valid_until
 * @property string|null $message
 * @property QuoteStatus $status
 * @property CarbonImmutable|null $created_at
 * @property-read Rfq $rfq
 * @property-read Company $company
 */
#[Fillable(['currency', 'lead_time_min_days', 'lead_time_max_days', 'sample_terms', 'valid_until', 'message'])]
class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'lead_time_min_days' => 'integer',
            'lead_time_max_days' => 'integer',
            'valid_until' => 'date',
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

    /**
     * @return HasMany<QuoteTier, $this>
     */
    public function tiers(): HasMany
    {
        return $this->hasMany(QuoteTier::class)->orderBy('min_qty');
    }

    /**
     * The tier that applies to a quantity: the highest starting quantity at
     * or below it (same rule as PriceForQuantity). Expects tiers loaded.
     */
    public function tierFor(int $quantity): ?QuoteTier
    {
        return $this->tiers->sortByDesc('min_qty')->first(fn (QuoteTier $tier) => $tier->min_qty <= $quantity);
    }

    public function leadTimeLabel(): string
    {
        return $this->lead_time_min_days === $this->lead_time_max_days
            ? "{$this->lead_time_min_days} days"
            : "{$this->lead_time_min_days}–{$this->lead_time_max_days} days";
    }
}
