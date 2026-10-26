<?php

namespace App\Http\Requests\Admin;

use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * What a marketplace admin may change about their marketplace. The domain,
 * slug and status stay with the platform admin.
 */
class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $phone = ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]{7,30}$/'];

        return [
            'name' => ['required', 'string', 'max:100'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'default_currency' => ['required', Rule::in(Money::currencies())],
            'tagline' => ['nullable', 'string', 'max:200'],
            'about' => ['nullable', 'string', 'max:2000'],
            'support_phone' => $phone,
            'whatsapp_number' => $phone,
            'address' => ['nullable', 'string', 'max:300'],
            'business_hours' => ['nullable', 'string', 'max:150'],
            'facebook_url' => ['nullable', 'url:https', 'max:255'],
            'instagram_url' => ['nullable', 'url:https', 'max:255'],
            'x_url' => ['nullable', 'url:https', 'max:255'],
            'linkedin_url' => ['nullable', 'url:https', 'max:255'],
            'require_account_approval' => ['required', Rule::in(['0', '1'])],
            'vat_percent' => ['required', 'numeric', 'min:0', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'support_phone.regex' => 'Enter a phone number with digits only, e.g. +250 788 123 456.',
            'whatsapp_number.regex' => 'Enter a phone number with digits only, e.g. +250 788 123 456.',
            '*.url' => 'Enter a full link starting with https://.',
        ];
    }
}
