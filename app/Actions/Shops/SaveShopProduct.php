<?php

namespace App\Actions\Shops;

use App\Actions\Media\SyncGallery;
use App\Enums\PlanLimit;
use App\Models\Shop;
use App\Models\ShopProduct;
use App\Models\ShopProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveShopProduct
{
    public function __construct(private SyncGallery $syncGallery) {}

    /**
     * Create or update a shop item and its photos. Available items count
     * against the seller plan's "listed products" limit, like wholesale
     * listings. The featured photo's thumbnail is copied to `image` for
     * cards and the cart.
     *
     * @param  array<string, mixed>  $attributes  Validated fields, price in minor units
     * @param  list<UploadedFile>  $uploads
     * @param  list<int>  $removeImageIds
     */
    public function handle(Shop $shop, ?ShopProduct $product, array $attributes, array $uploads = [], array $removeImageIds = [], ?string $cover = null): ShopProduct
    {
        return DB::transaction(function () use ($shop, $product, $attributes, $uploads, $removeImageIds, $cover): ShopProduct {
            $company = $shop->company()->lockForUpdate()->firstOrFail();
            $becomesAvailable = (bool) ($attributes['is_active'] ?? false) && ! ($product->is_active ?? false);

            if ($becomesAvailable) {
                if ($company->effectivePlan() === null) {
                    throw ValidationException::withMessages(['is_active' => 'Choose a seller plan before listing items.']);
                }

                if (! $company->withinPlanLimit(PlanLimit::MAX_PRODUCTS, $shop->products()->where('is_active', true)->count())) {
                    throw ValidationException::withMessages([
                        'is_active' => "Your plan allows {$company->planLimit(PlanLimit::MAX_PRODUCTS)} listed items. Hide one or upgrade your plan.",
                    ]);
                }
            }

            $product ??= tap(new ShopProduct, fn (ShopProduct $new) => $new->shop()->associate($shop));
            $product->fill($attributes);
            $product->save();

            if ($uploads !== [] || $removeImageIds !== [] || $cover !== null) {
                /** @var ShopProductImage|null $featured */
                $featured = $this->syncGallery
                    ->handle($product->images(), "shop-products/{$shop->id}/{$product->id}", $uploads, $removeImageIds, $cover, ShopProduct::MAX_IMAGES)
                    ->first();

                $product->forceFill(['image' => $featured?->thumbnail_path])->save();
            }

            return $product;
        });
    }
}
