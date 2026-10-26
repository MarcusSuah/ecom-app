<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\CurrencyRateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * "1 base = rate quote". Rows without a tenant are fetched for every
 * marketplace; a marketplace's own row is a manual override and wins.
 * Not tenant-scoped on purpose, so the shared rows stay visible.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $base
 * @property string $quote
 * @property string $rate
 * @property string $source
 * @property CarbonImmutable $fetched_at
 */
class CurrencyRate extends Model
{
    /** @use HasFactory<CurrencyRateFactory> */
    use HasFactory;

    public const SOURCE_MANUAL = 'manual';

    protected $fillable = ['tenant_id', 'base', 'quote', 'rate', 'source', 'fetched_at'];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:8',
            'fetched_at' => 'datetime',
        ];
    }

    /**
     * Every new rate, changed rate and removed override is written to the
     * rate history (CurrencyRateChange) with who made it.
     */
    protected static function booted(): void
    {
        static::saved(function (CurrencyRate $rate): void {
            if (! $rate->wasRecentlyCreated && ! $rate->wasChanged('rate')) {
                return;
            }

            $rate->recordChange($rate->wasRecentlyCreated ? null : $rate->getOriginal('rate'), $rate->rate, $rate->source);
        });

        static::deleted(function (CurrencyRate $rate): void {
            $rate->recordChange($rate->rate, null, CurrencyRateChange::SOURCE_RESET);
        });
    }

    private function recordChange(mixed $old, mixed $new, string $source): void
    {
        CurrencyRateChange::query()->create([
            'tenant_id' => $this->tenant_id,
            'base' => $this->base,
            'quote' => $this->quote,
            'old_rate' => $old,
            'new_rate' => $new,
            'source' => $source,
            'changed_by' => Auth::id(),
            'changed_at' => now(),
        ]);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * The fetched rates shared by every marketplace.
     *
     * @param  Builder<CurrencyRate>  $query
     */
    public function scopeShared(Builder $query): void
    {
        $query->whereNull('tenant_id');
    }
}
