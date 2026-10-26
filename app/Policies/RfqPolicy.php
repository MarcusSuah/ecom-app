<?php

namespace App\Policies;

use App\Models\Rfq;
use App\Models\User;

/**
 * RFQs are tenant-scoped, so every Rfq reaching this policy belongs to the
 * current marketplace.
 */
class RfqPolicy
{
    /**
     * Any member can ask for quotes.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * The buyer's view with the quote comparison.
     */
    public function view(User $user, Rfq $rfq): bool
    {
        return $rfq->buyer_id === $user->id;
    }

    /**
     * Accept a quote or close the request.
     */
    public function manage(User $user, Rfq $rfq): bool
    {
        return $rfq->buyer_id === $user->id;
    }

    /**
     * A supplier may read, decline and quote only on requests sent to it.
     */
    public function respond(User $user, Rfq $rfq): bool
    {
        $company = $user->currentCompany();

        return $company !== null && $rfq->recipients()->where('company_id', $company->id)->exists();
    }
}
