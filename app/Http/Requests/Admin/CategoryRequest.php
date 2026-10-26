<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use App\Support\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category instanceof Category
            ? $this->user()->can('update', $category)
            : $this->user()->can('create', Category::class);
    }

    /**
     * Categories are at most two levels deep: a parent must itself be a
     * top-level category of this marketplace, and never the category itself.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = app(CurrentTenant::class)->id();
        $category = $this->route('category');
        $categoryId = $category instanceof Category ? $category->id : null;

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('categories', 'name')->where('tenant_id', $tenantId)->ignore($categoryId),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('categories', 'id')->where(fn (Builder $query) => $query
                    ->where('tenant_id', $tenantId)
                    ->whereNull('parent_id')),
                Rule::notIn(array_filter([$categoryId])),
            ],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
            // A parent that is not listed yet is created as a top-level category first.
            'new_parent_name' => [
                'nullable', 'string', 'max:100', 'prohibits:parent_id', 'different:name',
                Rule::unique('categories', 'name')->where('tenant_id', $tenantId),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parent_id.exists' => 'Choose a top-level category as the parent.',
            'parent_id.not_in' => 'A category cannot be its own parent.',
            'new_parent_name.unique' => 'A category with this name already exists. Choose it from the list instead.',
            'new_parent_name.different' => 'The new parent needs a different name from this category.',
            'new_parent_name.prohibits' => 'Choose an existing parent or type a new one, not both.',
        ];
    }
}
