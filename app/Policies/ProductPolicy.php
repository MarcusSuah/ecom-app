<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/**
 * Products are tenant-scoped, so every Product reaching this policy already
 * belongs to the current marketplace.
 */
class ProductPolicy
{
    /**
     * Super admins bypass all product authorization checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('super_admin') ? true : null;
    }

    /**
     * The supplier's own product list.
     */
    public function viewAny(User $user): bool
    {
        return $this->ownsSupplierCompany($user);
    }

    /**
     * Active products are public; others only to their supplier and staff.
     */
    public function view(?User $user, Product $product): bool
    {
        // Listed products of a suspended or inactive business are hidden like drafts.
        if ($product->isActive() && $product->company->isListable()) {
            return true;
        }

        return $user !== null && ($this->owns($user, $product) || $user->can('companies.view-any'));
    }

    /**
     * Only supplier companies list wholesale products.
     */
    public function create(User $user): bool
    {
        return $this->ownsSupplierCompany($user);
    }

    public function update(User $user, Product $product): bool
    {
        return $this->owns($user, $product);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->owns($user, $product);
    }

    private function owns(User $user, Product $product): bool
    {
        return $product->company->owner_id === $user->id;
    }

    private function ownsSupplierCompany(User $user): bool
    {
        return $user->currentCompany()?->isSupplier() ?? false;
    }
}
