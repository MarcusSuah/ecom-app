<?php

namespace App\Http\Requests\Billing;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Recording an offline payment. Authorization is done by the controller,
 * which knows whether the subscriber is a tenant or a company.
 */
class ActivatePlanRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'periods' => ['required', 'integer', 'min:1', 'max:36'],
            'reference' => ['nullable', 'string', 'max:100'],
        ];
    }
}
