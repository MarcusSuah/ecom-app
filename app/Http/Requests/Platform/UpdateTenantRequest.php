<?php

namespace App\Http\Requests\Platform;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isPlatformAdmin();
    }

    /**
     * The slug is not editable: it is the marketplace's subdomain and is
     * part of every link already shared.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Tenant $tenant */
        $tenant = $this->route('tenant');

        return [
            'name' => ['required', 'string', 'max:150'],
            'domain' => ['nullable', 'string', 'max:255', 'regex:/^([a-z0-9-]+\.)+[a-z]{2,}$/', Rule::unique('tenants', 'domain')->ignore($tenant->id)],
            'default_currency' => ['required', Rule::in(Money::currencies())],
            'support_email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', Rule::enum(TenantStatus::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'domain' => $this->filled('domain') ? Str::lower((string) $this->input('domain')) : null,
        ]);
    }
}
