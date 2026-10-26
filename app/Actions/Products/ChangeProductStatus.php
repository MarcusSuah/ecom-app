<?php

namespace App\Actions\Products;

use App\Enums\PlanLimit;
use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeProductStatus
{
    /**
     * Publish, unpublish (back to draft) or archive a product. Publishing
     * counts against the seller plan's "listed products" limit.
     */
    public function handle(Product $product, ProductStatus $status): Product
    {
        if ($product->status === $status) {
            return $product;
        }

        return DB::transaction(function () use ($product, $status): Product {
            if ($status === ProductStatus::ACTIVE) {
                $company = $product->company()->lockForUpdate()->firstOrFail();

                if ($product->priceTiers()->doesntExist()) {
                    throw ValidationException::withMessages(['status' => 'Add at least one price tier before publishing.']);
                }

                if ($company->effectivePlan() === null) {
                    throw ValidationException::withMessages(['status' => 'Choose a seller plan before publishing products.']);
                }

                $listed = $company->products()->active()->count();

                if (! $company->withinPlanLimit(PlanLimit::MAX_PRODUCTS, $listed)) {
                    throw ValidationException::withMessages([
                        'status' => "Your plan allows {$company->planLimit(PlanLimit::MAX_PRODUCTS)} listed products. Archive one or upgrade your plan to publish more.",
                    ]);
                }
            }

            $product->status = $status;
            $product->save();

            return $product;
        });
    }
}
