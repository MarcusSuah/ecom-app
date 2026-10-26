<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Delivery\CalculateDeliveryFee;
use App\Actions\Shops\FindShops;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\ShopProduct;
use App\Models\ShopProductImage;
use App\Support\CustomerLocation;
use App\Support\DisplayCurrency;
use App\Support\PageMeta;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class ShopController extends Controller
{
    public function index(Request $request, FindShops $findShops): Response
    {
        $location = CustomerLocation::get($request);
        $deliversOnly = $request->boolean('delivers');
        $shops = collect($findShops->handle($location, $deliversOnly));

        $perPage = 24;
        $page = max(1, $request->integer('page', 1));
        $paginator = new LengthAwarePaginator(
            $shops->forPage($page, $perPage)->values(),
            $shops->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return Inertia::render('catalog/shops/index', [
            'shops' => $paginator,
            'filters' => ['delivers' => $deliversOnly],
            'location' => $location,
            'neighbourhoods' => config('marketplace.neighbourhoods'),
            'roadFactor' => (float) config('marketplace.road_factor'),
        ]);
    }

    public function show(Request $request, Company $company, CalculateDeliveryFee $calculateDeliveryFee, DisplayCurrency $displayCurrency): Response
    {
        $shop = $company->isShop() && $company->isListable() ? $company->shop : null;
        abort_if($shop === null, 404);

        $location = CustomerLocation::get($request);
        $company->load('latestVerification');

        return Inertia::render('catalog/shops/show', [
            'meta' => PageMeta::make(
                $company->name,
                $company->description ?? "{$company->name} at {$shop->address_text}: order for ".($shop->offers_delivery ? 'delivery or pickup' : 'pickup').'.',
                $company->logoUrl(),
            ),
            'shop' => [
                'id' => $shop->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'description' => $company->description,
                'logo_url' => $company->logoUrl(),
                'is_verified' => $company->isVerified(),
                'address' => $shop->address_text,
                'landmark' => $shop->landmark,
                'phone' => $company->phone,
                'offers_pickup' => $shop->offers_pickup,
                'offers_delivery' => $shop->offers_delivery,
                'is_open' => $shop->isOpenAt(now()),
                'opening_hours' => $shop->opening_hours,
                'base_fee' => $displayCurrency->format($shop->base_fee, $shop->currency),
                'per_km_rate' => $displayCurrency->format($shop->per_km_rate, $shop->currency),
                'free_threshold' => $shop->free_threshold !== null ? $displayCurrency->format($shop->free_threshold, $shop->currency) : null,
                'delivery_radius_km' => (float) $shop->delivery_radius_km,
                'delivery' => $location ? $calculateDeliveryFee->handle($shop, $location['lat'], $location['lng'], 0)->toArray() : null,
            ],
            'products' => $shop->products()->available()
                ->with(['category:id,name,slug', 'images'])
                ->orderBy('name')
                ->orderBy('id')
                ->paginate(24)
                ->through(fn (ShopProduct $product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'category' => $product->category->name,
                    'price' => $displayCurrency->format($product->price, $shop->currency),
                    'price_listed' => $displayCurrency->listed($product->price, $shop->currency),
                    'stock' => $product->stock,
                    'image_url' => $product->imageUrl(),
                    'category_slug' => $product->category->slug,
                    // Featured photo first; the rest show in the item's detail view.
                    'images' => $product->images->map(fn (ShopProductImage $image) => ['id' => $image->id, 'url' => $image->url(), 'thumbnail_url' => $image->thumbnailUrl()])->values(),
                    ...$product->stockBadge(),
                ]),
            'location' => $location,
            'canOrder' => $request->user()?->id !== $company->owner_id,
        ]);
    }
}
