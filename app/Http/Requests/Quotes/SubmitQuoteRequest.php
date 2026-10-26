<?php

namespace App\Http\Requests\Quotes;

use App\Models\Rfq;
use App\Support\Money;
use App\Support\QuoteTierRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SubmitQuoteRequest extends FormRequest
{
    public const MAX_TIERS = 10;

    public function authorize(): bool
    {
        return $this->user()->can('respond', $this->rfq());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'currency' => ['required', Rule::in(Money::currencies())],
            'lead_time_min_days' => ['required', 'integer', 'min:0', 'max:365'],
            'lead_time_max_days' => ['required', 'integer', 'gte:lead_time_min_days', 'max:365'],
            'sample_terms' => ['nullable', 'string', 'max:500'],
            'valid_until' => ['required', 'date', 'after_or_equal:today', 'before:+1 year'],
            'message' => ['nullable', 'string', 'max:5000'],
            'tiers' => ['required', 'array', 'min:1', 'max:'.self::MAX_TIERS],
            'tiers.*.min_qty' => ['required', 'integer', 'min:1'],
            'tiers.*.unit_price' => ['required', 'decimal:0,2', 'gt:0'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                foreach (QuoteTierRules::violations($this->tiers(), $this->rfq()->quantity) as $key => $message) {
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
            'tiers.*.unit_price.gt' => 'The unit price must be more than zero.',
            'lead_time_max_days.gte' => 'The longest lead time cannot be shorter than the shortest.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function quoteAttributes(): array
    {
        return $this->safe()->except('tiers');
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

    public function rfq(): Rfq
    {
        $rfq = $this->route('rfq');

        return $rfq instanceof Rfq ? $rfq : abort(404);
    }
}
