<?php

namespace App\Http\Requests\Rfqs;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Support\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The storefront's inquiry list: several listings with quantities, sent
 * as one request per listing to its supplier. Guests leave contact details.
 */
class StoreInquiryRequest extends FormRequest
{
    public const MAX_ITEMS = 20;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = app(CurrentTenant::class)->id();

        return [
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'items.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where(fn (Builder $query) => $query
                ->where('tenant_id', $tenantId)
                ->where('status', ProductStatus::ACTIVE->value))],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000000'],
            'deliver_city' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'guest_name' => [Rule::requiredIf($this->user() === null), 'nullable', 'string', 'max:100'],
            'guest_email' => [Rule::requiredIf($this->user() === null), 'nullable', 'string', 'email', 'max:255'],
            'guest_phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]{7,30}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Your inquiry list is empty.',
            'items.max' => 'Send at most '.self::MAX_ITEMS.' products at once.',
            'items.*.product_id.exists' => 'One of the products is no longer listed. Remove it and try again.',
            'deliver_city.required' => 'Say where the goods should be delivered.',
            'guest_name.required' => 'Tell suppliers who the inquiry is from.',
            'guest_email.required' => 'Enter your email so we can send you the quotes.',
            'guest_phone.required' => 'Enter a phone number suppliers can call about the inquiry.',
            'guest_phone.regex' => 'Enter a phone number with digits only, e.g. +250 788 123 456.',
        ];
    }

    /**
     * @return list<array{product_id: int, quantity: int}>
     */
    public function items(): array
    {
        return array_values(array_map(fn (array $item) => [
            'product_id' => (int) $item['product_id'],
            'quantity' => (int) $item['quantity'],
        ], $this->validated('items')));
    }

    /**
     * A listing with a stock count cannot be requested beyond it.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_map(fn (int $index) => ["items.{$index}.product_id", "items.{$index}.quantity"], array_keys((array) $this->input('items', []))) as [$productKey, $quantityKey]) {
                $product = Product::query()->whereKey((int) $this->input($productKey))->first();
                $quantity = (int) $this->input($quantityKey);

                if ($product?->stock !== null && $quantity > $product->stock) {
                    $validator->errors()->add($quantityKey, 'Only '.number_format($product->stock)." {$product->unit->value} of {$product->name} are in stock.");
                }
            }
        }];
    }
}
