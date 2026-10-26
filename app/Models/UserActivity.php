<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing a person did: a sign-in, failed attempt, page or change.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int|null $user_id
 * @property string|null $user_name
 * @property string|null $email
 * @property string $event
 * @property string|null $description
 * @property string|null $method
 * @property string|null $url
 * @property string|null $route_name
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $device
 * @property string|null $browser
 * @property string|null $platform
 * @property string|null $country
 * @property string|null $region
 * @property string|null $city
 * @property bool $is_suspicious
 * @property CarbonImmutable|null $created_at
 * @property-read User|null $user
 */
class UserActivity extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id', 'user_id', 'user_name', 'email', 'event', 'description', 'method', 'url', 'route_name',
        'ip_address', 'user_agent', 'device', 'browser', 'platform', 'country', 'region', 'city', 'is_suspicious',
    ];

    protected function casts(): array
    {
        return ['is_suspicious' => 'boolean', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Keep 180 days (php artisan model:prune — scheduled daily). */
    /**
     * Old activity is removed by model:prune (scheduled daily).
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subMonths((int) config('marketplace.activity.retention_months', 6)));
    }
}
