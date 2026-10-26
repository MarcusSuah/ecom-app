<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Database\Factories\ShopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The location and delivery settings of a shop company.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $company_id
 * @property string $lat
 * @property string $lng
 * @property string $address_text
 * @property string|null $landmark
 * @property bool $offers_pickup
 * @property bool $offers_delivery
 * @property int $base_fee
 * @property int $per_km_rate
 * @property int|null $free_threshold
 * @property string $delivery_radius_km
 * @property array<string, array{open: string, close: string}|null>|null $opening_hours
 * @property string $currency
 * @property-read Company $company
 */
#[Fillable(['lat', 'lng', 'address_text', 'landmark', 'offers_pickup', 'offers_delivery', 'base_fee', 'per_km_rate', 'free_threshold', 'delivery_radius_km', 'opening_hours', 'currency'])]
class Shop extends Model
{
    /** @use HasFactory<ShopFactory> */
    use BelongsToTenant, HasFactory;

    public const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'offers_pickup' => 'boolean',
            'offers_delivery' => 'boolean',
            'base_fee' => 'integer',
            'per_km_rate' => 'integer',
            'free_threshold' => 'integer',
            'delivery_radius_km' => 'decimal:2',
            'opening_hours' => 'array',
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
     * @return HasMany<ShopProduct, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(ShopProduct::class);
    }

    /**
     * Open hours are "HH:MM" pairs per weekday in the marketplace timezone;
     * a missing or null day is closed. A closing time before the opening
     * time runs past midnight.
     */
    public function isOpenAt(CarbonInterface $moment): bool
    {
        $local = $moment->setTimezone((string) config('marketplace.timezone'));
        $hours = $this->opening_hours[self::DAYS[$local->dayOfWeekIso - 1]] ?? null;

        if (! $hours) {
            return false;
        }

        $time = $local->format('H:i');

        return $hours['open'] <= $hours['close']
            ? $time >= $hours['open'] && $time < $hours['close']
            : $time >= $hours['open'] || $time < $hours['close'];
    }
}
