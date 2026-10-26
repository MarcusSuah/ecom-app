<?php

namespace App\Policies;

use App\Enums\PlanAudience;
use App\Models\Plan;
use App\Models\User;
use App\Support\CurrentTenant;

/**
 * Marketplace admins manage their own seller plans. Marketplace plans are
 * managed only by platform admins (who pass every check via Gate::before).
 * Plans are not tenant-scoped, so ownership is checked here.
 */
class PlanPolicy
{
    public function __construct(private CurrentTenant $currentTenant) {}

    public function viewAny(User $user): bool
    {
        return $user->can('seller-plans.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('seller-plans.manage');
    }

    public function update(User $user, Plan $plan): bool
    {
        return $user->can('seller-plans.manage') && $this->isSellerPlanOfThisMarketplace($plan);
    }

    public function delete(User $user, Plan $plan): bool
    {
        return $this->update($user, $plan);
    }

    private function isSellerPlanOfThisMarketplace(Plan $plan): bool
    {
        return $plan->audience === PlanAudience::SELLER
            && $plan->tenant_id !== null
            && $plan->tenant_id === $this->currentTenant->id();
    }
}
