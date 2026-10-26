<?php

namespace App\Actions\Products;

use App\Enums\ProductStatus;
use App\Models\Company;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SaveProduct
{
    /**
     * Create or update a product and replace its price tiers. Tiers must
     * already satisfy PriceTierRules (checked by SaveProductRequest). New
     * products start as drafts; the status is changed by ChangeProductStatus.
     *
     * @param  array<string, mixed>  $attributes  Validated product fields
     * @param  list<array{min_qty: int, unit_price: int}>  $tiers  Prices in minor units
     */
    public function handle(Company $company, ?Product $product, array $attributes, array $tiers): Product
    {
        if ($tiers === []) {
            throw new InvalidArgumentException('A product needs at least one price tier.');
        }

        return DB::transaction(function () use ($company, $product, $attributes, $tiers): Product {
            $product ??= tap(new Product, function (Product $new) use ($company): void {
                $new->company()->associate($company);
                $new->status = ProductStatus::DRAFT;
            });

            $product->fill($attributes);
            $product->sample_price = $product->sample_available ? $product->sample_price : null;
            $product->min_unit_price = min(array_column($tiers, 'unit_price'));

            Product::withoutSyncingToSearch(fn () => $product->save());

            $product->priceTiers()->delete();
            $product->priceTiers()->createMany($tiers);
            $product->unsetRelation('priceTiers');

            $product->searchable();

            return $product;
        });
    }
}
