<?php

namespace App\Support;

use App\Models\Address;
use Illuminate\Http\Request;

/**
 * Where the customer wants things delivered: the location picked in this
 * session (guests and members alike), otherwise a member's default saved
 * address. Session locations are per marketplace.
 */
final class CustomerLocation
{
    /**
     * @return array{lat: float, lng: float, label: string, description: string|null, landmark: string|null, address_id: int|null}|null
     */
    public static function get(Request $request): ?array
    {
        /** @var array{lat: float, lng: float, label: string, description: string|null, landmark: string|null, address_id: int|null}|null $location */
        $location = $request->session()->get(self::key());

        if ($location) {
            return $location;
        }

        $address = $request->user()
            ? Address::query()->where('user_id', $request->user()->id)->orderByDesc('is_default')->latest('id')->first()
            : null;

        return $address ? self::fromAddress($address) : null;
    }

    /**
     * @param  array{lat: float, lng: float, label: string, description?: string|null, landmark?: string|null, address_id?: int|null}  $location
     */
    public static function set(Request $request, array $location): void
    {
        $request->session()->put(self::key(), [
            'lat' => (float) $location['lat'],
            'lng' => (float) $location['lng'],
            'label' => $location['label'],
            'description' => $location['description'] ?? null,
            'landmark' => $location['landmark'] ?? null,
            'address_id' => $location['address_id'] ?? null,
        ]);
    }

    public static function forget(Request $request): void
    {
        $request->session()->forget(self::key());
    }

    /**
     * @return array{lat: float, lng: float, label: string, description: string|null, landmark: string|null, address_id: int|null}
     */
    public static function fromAddress(Address $address): array
    {
        return [
            'lat' => (float) $address->lat,
            'lng' => (float) $address->lng,
            'label' => $address->label,
            'description' => $address->description,
            'landmark' => $address->landmark,
            'address_id' => $address->id,
        ];
    }

    private static function key(): string
    {
        return 'customer_location.'.(app(CurrentTenant::class)->id() ?? 'none');
    }
}
