<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * An address refused on every marketplace until it is unblocked or expires.
 *
 * @property int $id
 * @property string $ip_address
 * @property string|null $reason
 * @property int|null $blocked_by
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $created_at
 * @property-read User|null $blocker
 */
class BlockedIp extends Model
{
    protected $fillable = ['ip_address', 'reason', 'blocked_by', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('blocked_ips'));
        static::deleted(fn () => Cache::forget('blocked_ips'));
    }

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /** Cached list of currently blocked IPs (checked on every request). */
    public static function isBlocked(?string $ip): bool
    {
        if (! $ip) {
            return false;
        }

        return in_array($ip, Cache::remember('blocked_ips', 60, fn () => static::active()->pluck('ip_address')->all()), true);
    }
}
