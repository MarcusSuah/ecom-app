<?php

namespace App\Http\Requests\Rfqs;

use App\Enums\ProductStatus;
use App\Enums\ProductUnit;
use App\Models\Product;
use App\Support\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Guests may submit too, with a name and email so suppliers' quotes can
 * reach them. Signing in is only needed to accept a quote and order.
 */
class StoreRfqRequest extends FormRequest
{
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
            'title' => ['required', 'string', 'max:200'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('tenant_id', $tenantId)],
            'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->where(fn (Builder $query) => $query
                ->where('tenant_id', $tenantId)
                ->where('status', ProductStatus::ACTIVE->value))],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000000'],
            'unit' => ['required', Rule::enum(ProductUnit::class)],
            'deliver_city' => ['required', 'string', 'max:100'],
            'needed_by' => ['nullable', 'date', 'after:today'],
            'notes' => ['nullable', 'string', 'max:5000'],
            // Guests leave contact details instead of signing in.
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
            'title.required' => 'Say what you need.',
            'needed_by.after' => 'Choose a date after today.',
            'guest_name.required' => 'Tell suppliers who the request is from.',
            'guest_email.required' => 'Enter your email so we can send you the quotes.',
            'guest_phone.required' => 'Enter a phone number suppliers can call about the request.',
            'guest_phone.regex' => 'Enter a phone number with digits only, e.g. +250 788 123 456.',
        ];
    }

    /**
     * A listing with a stock count cannot be requested beyond it.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ([['product_id', 'quantity']] as [$productKey, $quantityKey]) {
                $product = Product::query()->whereKey((int) $this->input($productKey))->first();
                $quantity = (int) $this->input($quantityKey);

                if ($product?->stock !== null && $quantity > $product->stock) {
                    $validator->errors()->add($quantityKey, 'Only '.number_format($product->stock)." {$product->unit->value} of {$product->name} are in stock.");
                }
            }
        }];
    }
}
