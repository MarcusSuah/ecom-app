<?php

namespace App\Actions\Shops;

use App\Models\Company;
use App\Models\Shop;
use Illuminate\Validation\ValidationException;

class SaveShopSettings
{
    /**
     * Create or update a shop company's location and delivery settings.
     *
     * @param  array<string, mixed>  $attributes  Validated settings, fees already in minor units
     */
    public function handle(Company $company, array $attributes): Shop
    {
        if (! $company->isShop()) {
            throw ValidationException::withMessages(['company' => 'Only shops have delivery settings.']);
        }

        if (empty($attributes['offers_pickup']) && empty($attributes['offers_delivery'])) {
            throw ValidationException::withMessages(['offers_pickup' => 'Offer pickup, delivery or both.']);
        }

        $shop = $company->shop ?? new Shop;
        $shop->company()->associate($company);
        $shop->fill($attributes);
        $shop->save();

        return $shop;
    }
}
