<?php

namespace App\Http\Requests\Shops;

use App\Http\Requests\Concerns\ValidatesGallery;
use App\Models\ShopProduct;
use App\Support\CurrentTenant;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShopProductRequest extends FormRequest
{
    use ValidatesGallery;

    public function authorize(): bool
    {
        $product = $this->route('shopProduct');

        return $product instanceof ShopProduct
            ? $this->user()->can('update', $product)
            : $this->user()->can('create', ShopProduct::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('tenant_id', app(CurrentTenant::class)->id())],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'decimal:0,2', 'gt:0', 'max:100000000'],
            'stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['boolean'],
            ...$this->galleryRules(ShopProduct::MAX_IMAGES),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->galleryMessages();
    }

    /**
     * @return array<string, mixed>
     */
    public function productAttributes(string $currency): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'category_id' => $this->integer('category_id'),
            'description' => $this->filled('description') ? $this->string('description')->toString() : null,
            'price' => Money::toMinor($this->string('price')->toString(), $currency),
            'stock' => $this->integer('stock'),
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
