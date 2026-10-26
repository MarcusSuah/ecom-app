<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

/**
 * Companies are tenant-scoped, so every Company reaching this policy
 * already belongs to the current marketplace.
 */
class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('companies.view-any');
    }

    /**
     * One company per user on each marketplace.
     */
    public function create(User $user): bool
    {
        return $user->companies()->doesntExist();
    }

    public function update(User $user, Company $company): bool
    {
        return $user->managesCompany($company);
    }

    public function manageBilling(User $user, Company $company): bool
    {
        return $company->owner_id === $user->id;
    }

    public function submitVerification(User $user, Company $company): bool
    {
        return $company->owner_id === $user->id;
    }

    public function feature(User $user, Company $company): bool
    {
        return $user->can('companies.feature');
    }

    public function suspend(User $user, Company $company): bool
    {
        return $user->can('companies.suspend');
    }

    /**
     * Work in the business's seller screens on its behalf (ActingCompany).
     */
    public function manage(User $user, Company $company): bool
    {
        return $user->can('companies.manage') && $company->owner_id !== $user->id;
    }

    public function manageSubscription(User $user, Company $company): bool
    {
        return $user->can('seller-subscriptions.manage');
    }
}
