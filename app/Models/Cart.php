<?php

namespace App\Models;

use App\Enums\Fulfilment;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property array<int, string>|null $fulfilment_choices Keyed by shop id
 */
#[Fillable(['fulfilment_choices'])]
class Cart extends Model
{
    use BelongsToTenant;

    /** A cart left this long without a change is emptied (ManageCart::expireIfIdle). */
    public const IDLE_MINUTES = 10;

    /**
     * When the items are dropped if nothing changes; null when empty.
     */
    public function expiresAt(): ?CarbonInterface
    {
        return $this->updated_at?->copy()->addMinutes(self::IDLE_MINUTES);
    }

    protected function casts(): array
    {
        return [
            'fulfilment_choices' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<CartItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * The customer's choice for a shop, or the shop's default (delivery
     * when it delivers, otherwise pickup).
     */
    public function fulfilmentFor(Shop $shop): Fulfilment
    {
        $choice = Fulfilment::tryFrom($this->fulfilment_choices[$shop->id] ?? '');

        return match (true) {
            $choice === Fulfilment::DELIVERY && $shop->offers_delivery, $choice === Fulfilment::PICKUP && $shop->offers_pickup => $choice,
            $shop->offers_delivery => Fulfilment::DELIVERY,
            default => Fulfilment::PICKUP,
        };
    }
}
