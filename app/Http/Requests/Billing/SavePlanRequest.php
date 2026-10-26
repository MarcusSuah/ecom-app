<?php

namespace App\Http\Requests\Billing;

use App\Enums\BillingInterval;
use App\Enums\PlanAudience;
use App\Models\Plan;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create or edit a plan. The price is typed in major units ("25000",
 * "19.99") and converted to stored minor units by SavePlan.
 */
abstract class SavePlanRequest extends FormRequest
{
    abstract public function audience(): PlanAudience;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'decimal:0,2', 'min:0', 'max:100000000'],
            'currency' => ['required', Rule::in(Money::currencies())],
            'interval' => ['required', Rule::enum(BillingInterval::class)],
            'trial_days' => ['required', 'integer', 'min:0', 'max:90'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
            'limits' => ['array'],
        ];

        foreach ($this->audience()->limits() as $limit) {
            $rules['limits.'.$limit->value] = $limit->isSwitch()
                ? ['boolean']
                : ['nullable', 'integer', 'min:0', 'max:1000000'];
        }

        return $rules;
    }

    /**
     * @return array{name: string, description: string|null, price: string, currency: string, interval: string, trial_days: int, is_default: bool, is_active: bool, sort_order: int}
     */
    public function planAttributes(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'description' => $this->filled('description') ? $this->string('description')->toString() : null,
            'price' => $this->string('price')->toString(),
            'currency' => $this->string('currency')->toString(),
            'interval' => $this->string('interval')->toString(),
            'trial_days' => $this->integer('trial_days'),
            'is_default' => $this->boolean('is_default'),
            'is_active' => $this->boolean('is_active', true),
            'sort_order' => $this->integer('sort_order'),
        ];
    }

    /**
     * Only the limits that apply to this plan's audience are kept.
     *
     * @return array<string, int|bool|null>
     */
    public function limits(): array
    {
        $limits = [];

        foreach ($this->audience()->limits() as $limit) {
            $value = $this->input('limits.'.$limit->value);
            $limits[$limit->value] = $limit->isSwitch()
                ? (bool) $value
                : ($value === null || $value === '' ? null : (int) $value);
        }

        return $limits;
    }

    protected function plan(): ?Plan
    {
        $plan = $this->route('plan');

        return $plan instanceof Plan ? $plan : null;
    }
}
