<?php

namespace App\Http\Requests\Platform;

use App\Enums\PlanAudience;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isPlatformAdmin();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:63', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', Rule::unique('tenants', 'slug'), Rule::notIn(['www', 'admin', 'platform', 'api'])],
            'domain' => ['nullable', 'string', 'max:255', 'regex:/^([a-z0-9-]+\.)+[a-z]{2,}$/', Rule::unique('tenants', 'domain')],
            'default_currency' => ['required', Rule::in(Money::currencies())],
            'support_email' => ['nullable', 'email', 'max:255'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where(fn (Builder $query) => $query
                ->where('audience', PlanAudience::MARKETPLACE->value)
                ->whereNull('tenant_id')
                ->where('is_active', true))],
        ];
    }

    /**
     * @return array{name: string, slug: string, domain: string|null, default_currency: string, support_email: string|null, admin_name: string, admin_email: string, plan_id: int}
     */
    public function tenantAttributes(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'slug' => $this->string('slug')->toString(),
            'domain' => $this->filled('domain') ? $this->string('domain')->toString() : null,
            'default_currency' => $this->string('default_currency')->toString(),
            'support_email' => $this->filled('support_email') ? $this->string('support_email')->toString() : null,
            'admin_name' => $this->string('admin_name')->toString(),
            'admin_email' => $this->string('admin_email')->toString(),
            'plan_id' => $this->integer('plan_id'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'Use lowercase letters, numbers and dashes only; this becomes the subdomain.',
            'domain.regex' => 'Enter a domain like shop.example.com, without https://.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::lower((string) $this->input('slug')),
            'domain' => $this->filled('domain') ? Str::lower((string) $this->input('domain')) : null,
        ]);
    }
}
