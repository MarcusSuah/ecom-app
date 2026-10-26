<?php

namespace App\Actions\Billing;

use App\Enums\PlanAudience;
use App\Models\Plan;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class SavePlan
{
    /**
     * Create or update a plan. Existing subscribers keep the price they
     * signed up at (it is copied onto their subscription). Only one plan
     * per marketplace is the default for new sellers.
     *
     * @param  array{name: string, description: string|null, price: string, currency: string, interval: string, trial_days: int, is_default: bool, is_active: bool, sort_order: int}  $attributes
     * @param  array<string, int|bool|null>  $limits
     */
    public function handle(?Plan $plan, PlanAudience $audience, ?int $tenantId, array $attributes, array $limits): Plan
    {
        return DB::transaction(function () use ($plan, $audience, $tenantId, $attributes, $limits): Plan {
            $plan ??= new Plan(['audience' => $audience, 'tenant_id' => $tenantId]);

            $plan->fill([
                'name' => $attributes['name'],
                'description' => $attributes['description'],
                'price' => Money::toMinor($attributes['price'], $attributes['currency']),
                'currency' => $attributes['currency'],
                'interval' => $attributes['interval'],
                'trial_days' => $attributes['trial_days'],
                'is_default' => $attributes['is_default'],
                'is_active' => $attributes['is_active'],
                'sort_order' => $attributes['sort_order'],
                'limits' => $limits,
            ]);
            $plan->save();

            if ($plan->is_default) {
                Plan::query()
                    ->where('audience', $audience)
                    ->where('tenant_id', $tenantId)
                    ->whereKeyNot($plan->id)
                    ->update(['is_default' => false]);
            }

            return $plan;
        });
    }
}
