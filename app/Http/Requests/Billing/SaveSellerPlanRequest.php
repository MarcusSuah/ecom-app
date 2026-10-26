<?php

namespace App\Http\Requests\Billing;

use App\Enums\PlanAudience;
use App\Models\Plan;

class SaveSellerPlanRequest extends SavePlanRequest
{
    public function authorize(): bool
    {
        $plan = $this->plan();

        return $plan
            ? $this->user()->can('update', $plan)
            : $this->user()->can('create', Plan::class);
    }

    public function audience(): PlanAudience
    {
        return PlanAudience::SELLER;
    }
}
