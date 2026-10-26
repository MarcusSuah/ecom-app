<?php

namespace App\Http\Controllers\Shop;

use App\Actions\Shops\SaveShopSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shops\ShopSettingsRequest;
use App\Models\Company;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A shop's location, pickup/delivery options, fees and opening hours.
 */
class SettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $company = $this->shopCompany($request);
        $shop = $company->shop;

        return Inertia::render('shop/settings', [
            'shop' => $shop ? [
                'lat' => (float) $shop->lat,
                'lng' => (float) $shop->lng,
                'address_text' => $shop->address_text,
                'landmark' => $shop->landmark ?? '',
                'offers_pickup' => $shop->offers_pickup,
                'offers_delivery' => $shop->offers_delivery,
                'currency' => $shop->currency,
                'base_fee' => Money::toMajor($shop->base_fee, $shop->currency),
                'per_km_rate' => Money::toMajor($shop->per_km_rate, $shop->currency),
                'free_threshold' => $shop->free_threshold !== null ? Money::toMajor($shop->free_threshold, $shop->currency) : '',
                'delivery_radius_km' => (string) (float) $shop->delivery_radius_km,
                'opening_hours' => $shop->opening_hours,
            ] : null,
            'defaults' => ['currency' => $company->tenant->default_currency],
            'currencies' => Money::currencies(),
            'neighbourhoods' => config('marketplace.neighbourhoods'),
        ]);
    }

    public function update(ShopSettingsRequest $request, SaveShopSettings $saveShopSettings): RedirectResponse
    {
        $saveShopSettings->handle($this->shopCompany($request), $request->shopAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Shop settings saved.']);

        return to_route('shop.settings.edit');
    }

    private function shopCompany(Request $request): Company
    {
        $company = $request->user()->currentCompany();
        abort_unless($company?->isShop(), 403);

        return $company;
    }
}
