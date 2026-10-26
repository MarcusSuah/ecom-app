<?php

namespace App\Http\Requests\Shops;

use App\Models\Shop;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Fees are typed in major units and stored in minor units of the shop's currency.
 */
class ShopSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->currentCompany()?->isShop() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'address_text' => ['required', 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'offers_pickup' => ['boolean'],
            'offers_delivery' => ['boolean'],
            'currency' => ['required', Rule::in(Money::currencies())],
            'base_fee' => ['required', 'decimal:0,2', 'min:0', 'max:10000000'],
            'per_km_rate' => ['required', 'decimal:0,2', 'min:0', 'max:10000000'],
            'free_threshold' => ['nullable', 'decimal:0,2', 'min:0', 'max:1000000000'],
            'delivery_radius_km' => ['required', 'numeric', 'min:0.5', 'max:100'],
            'opening_hours' => ['array'],
        ];

        foreach (Shop::DAYS as $day) {
            $rules["opening_hours.{$day}"] = ['nullable', 'array'];
            $rules["opening_hours.{$day}.open"] = ["required_with:opening_hours.{$day}", 'date_format:H:i'];
            $rules["opening_hours.{$day}.close"] = ["required_with:opening_hours.{$day}", 'date_format:H:i'];
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    public function shopAttributes(): array
    {
        $currency = $this->string('currency')->toString();
        $hours = [];

        foreach (Shop::DAYS as $day) {
            $open = $this->input("opening_hours.{$day}.open");
            $close = $this->input("opening_hours.{$day}.close");
            $hours[$day] = $open && $close ? ['open' => (string) $open, 'close' => (string) $close] : null;
        }

        return [
            'lat' => $this->float('lat'),
            'lng' => $this->float('lng'),
            'address_text' => $this->string('address_text')->toString(),
            'landmark' => $this->filled('landmark') ? $this->string('landmark')->toString() : null,
            'offers_pickup' => $this->boolean('offers_pickup'),
            'offers_delivery' => $this->boolean('offers_delivery'),
            'currency' => $currency,
            'base_fee' => Money::toMinor($this->string('base_fee')->toString(), $currency),
            'per_km_rate' => Money::toMinor($this->string('per_km_rate')->toString(), $currency),
            'free_threshold' => $this->filled('free_threshold') ? Money::toMinor($this->string('free_threshold')->toString(), $currency) : null,
            'delivery_radius_km' => $this->float('delivery_radius_km'),
            'opening_hours' => $hours,
        ];
    }
}
