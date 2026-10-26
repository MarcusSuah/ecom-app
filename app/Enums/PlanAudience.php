<?php

namespace App\Enums;

enum PlanAudience: string
{
    /** Sold by the platform to tenants that run a marketplace. */
    case MARKETPLACE = 'marketplace';

    /** Sold by a tenant to the supplier and shop companies on its marketplace. */
    case SELLER = 'seller';

    /**
     * @return list<PlanLimit>
     */
    public function limits(): array
    {
        return array_values(array_filter(PlanLimit::cases(), fn (PlanLimit $limit) => $limit->audience() === $this));
    }
}
