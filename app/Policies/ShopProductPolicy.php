<?php

namespace App\Policies;

use App\Models\ShopProduct;
use App\Models\User;

class ShopProductPolicy
{
    /**
     * Shop companies with delivery settings list items.
     */
    public function create(User $user): bool
    {
        $company = $user->currentCompany();

        return $company !== null && $company->isShop() && $company->shop()->exists();
    }

    public function update(User $user, ShopProduct $product): bool
    {
        return $user->managesCompany($product->shop->company);
    }

    public function delete(User $user, ShopProduct $product): bool
    {
        return $this->update($user, $product);
    }
}
