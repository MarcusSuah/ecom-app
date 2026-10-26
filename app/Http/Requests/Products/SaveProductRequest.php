<?php

namespace App\Http\Requests\Products;

use App\Enums\ProductUnit;
use App\Http\Requests\Concerns\ValidatesGallery;
use App\Models\Product;
use App\Support\CurrentTenant;
use App\Support\Money;
use App\Support\PriceTierRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create or edit a product with its price tiers. Prices are typed in major
 * units and converted to minor units of the product's currency.
 */
class SaveProductRequest extends FormRequest
{
    use ValidatesGallery;

    public const MAX_TIERS = 10;

    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product
            ? $this->user()->can('update', $product)
            : $this->user()->can('create', Product::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('tenant_id', app(CurrentTenant::class)->id())],
            'description' => ['nullable', 'string', 'max:10000'],
            'unit' => ['required', Rule::enum(ProductUnit::class)],
            'moq' => ['required', 'integer', 'min:1', 'max:10000000'],
            'lead_time_min_days' => ['required', 'integer', 'min:0', 'max:365'],
            'lead_time_max_days' => ['required', 'integer', 'gte:lead_time_min_days', 'max:365'],
            'currency' => ['required', Rule::in(Money::currencies())],
            'origin_country' => ['required', 'string', 'size:2', 'alpha'],
            'sample_available' => ['boolean'],
            'sample_price' => ['nullable', Rule::requiredIf(fn () => $this->boolean('sample_available')), 'decimal:0,2', 'min:0'],
            'tiers' => ['required', 'array', 'min:1', 'max:'.self::MAX_TIERS],
            'tiers.*.min_qty' => ['required', 'integer', 'min:1'],
            'tiers.*.unit_price' => ['required', 'decimal:0,2', 'gt:0'],
            // Units ready to ship; empty means made to order.
            'stock' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            ...$this->galleryRules(Product::MAX_IMAGES),
        ];
    }

    /**
     * Cross-row tier rules, reported against the row that breaks them.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                foreach (PriceTierRules::violations($this->tiers(), $this->integer('moq')) as $key => $message) {
                    $validator->errors()->add($key, $message);
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tiers.required' => 'Add at least one price tier.',
            'tiers.*.min_qty.required' => 'Enter the quantity this tier starts at.',
            'tiers.*.unit_price.required' => 'Enter the unit price for this tier.',
            'tiers.*.unit_price.gt' => 'The unit price must be more than zero.',
            'lead_time_max_days.gte' => 'The longest lead time cannot be shorter than the shortest.',
            'sample_price.required' => 'Enter the sample price, or 0 for free samples.',
            ...$this->galleryMessages(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function productAttributes(): array
    {
        $currency = $this->string('currency')->toString();

        return [
            ...$this->safe()->except(['tiers', 'sample_price', 'images', 'remove_images', 'cover']),
            'stock' => $this->filled('stock') ? $this->integer('stock') : null,
            'origin_country' => strtoupper($this->string('origin_country')->toString()),
            'sample_available' => $this->boolean('sample_available'),
            'sample_price' => $this->filled('sample_price') ? Money::toMinor($this->string('sample_price')->toString(), $currency) : null,
        ];
    }

    /**
     * @return list<array{min_qty: int, unit_price: int}>
     */
    public function tiers(): array
    {
        $currency = $this->string('currency')->toString();
        $tiers = [];

        foreach (array_values((array) $this->input('tiers', [])) as $tier) {
            $tiers[] = [
                'min_qty' => (int) ($tier['min_qty'] ?? 0),
                'unit_price' => Money::toMinor((string) ($tier['unit_price'] ?? '0'), $currency),
            ];
        }

        return $tiers;
    }
}
