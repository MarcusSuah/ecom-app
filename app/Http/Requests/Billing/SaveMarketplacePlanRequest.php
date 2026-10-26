<?php

namespace App\Http\Requests\Billing;

use App\Enums\PlanAudience;

/**
 * Only reachable through the platform.admin middleware.
 */
class SaveMarketplacePlanRequest extends SavePlanRequest
{
    public function authorize(): bool
    {
        return $this->user()->isPlatformAdmin();
    }

    public function audience(): PlanAudience
    {
        return PlanAudience::MARKETPLACE;
    }
}
