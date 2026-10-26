<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One change to an exchange rate, kept for accountability. Written by
 * CurrencyRate's model events; never edited.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $base
 * @property string $quote
 * @property string|null $old_rate
 * @property string|null $new_rate
 * @property string $source
 * @property int|null $changed_by
 * @property Carbon $changed_at
 * @property-read User|null $changer
 */
class CurrencyRateChange extends Model
{
    /** "reset": a marketplace went back to the shared daily rate. */
    public const SOURCE_RESET = 'reset';

    public $timestamps = false;

    protected $fillable = ['tenant_id', 'base', 'quote', 'old_rate', 'new_rate', 'source', 'changed_by', 'changed_at'];

    protected function casts(): array
    {
        return [
            'old_rate' => 'decimal:8',
            'new_rate' => 'decimal:8',
            'changed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Changes that affect a marketplace: the shared daily rate and its own overrides.
     *
     * @param  Builder<CurrencyRateChange>  $query
     */
    public function scopeAffecting(Builder $query, int $tenantId): void
    {
        $query->where(fn (Builder $query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenantId));
    }
}
