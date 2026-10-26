<?php

namespace App\Http\Controllers\Supplier;

use App\Actions\Media\SyncGallery;
use App\Actions\Products\AddProductImages;
use App\Actions\Products\ChangeProductStatus;
use App\Actions\Products\DeleteProduct;
use App\Actions\Products\SaveProduct;
use App\Enums\PlanLimit;
use App\Enums\ProductStatus;
use App\Enums\ProductUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Products\SaveProductRequest;
use App\Models\Category;
use App\Models\Company;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductImage;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A supplier's own products on the current marketplace.
 */
class ProductController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        Gate::authorize('viewAny', Product::class);

        // Staff who can edit any product have no list of their own.
        if ($request->user()->currentCompany() === null) {
            return to_route('company.create');
        }

        $company = $this->company($request);
        $status = ProductStatus::tryFrom($request->string('status')->toString());

        $products = $company->products()
            ->with(['category', 'coverImage'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'status' => $product->status->value,
                'category' => $product->category->name,
                'moq' => $product->moq,
                'unit' => $product->unit->value,
                'from_price' => $product->min_unit_price !== null ? Money::format($product->min_unit_price, $product->currency) : null,
                'thumbnail_url' => $product->coverImage?->thumbnailUrl(),
                'updated_at' => $product->updated_at?->toISOString(),
                ...$product->stockBadge(),
            ]);

        return Inertia::render('supplier/products/index', [
            'products' => $products,
            'filters' => ['status' => $status->value ?? ''],
            'listing' => [
                'listed' => $company->products()->active()->count(),
                'limit' => $company->planLimit(PlanLimit::MAX_PRODUCTS),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Product::class);

        return Inertia::render('supplier/products/form', [
            ...$this->formOptions(),
            'product' => null,
            'backUrl' => $this->backUrl($request),
            'defaults' => ['currency' => $this->company($request)->tenant->default_currency],
        ]);
    }

    public function store(SaveProductRequest $request, SaveProduct $saveProduct, SyncGallery $syncGallery): RedirectResponse
    {
        $product = DB::transaction(function () use ($request, $saveProduct, $syncGallery): Product {
            $product = $saveProduct->handle($this->company($request), null, $request->productAttributes(), $request->tiers());
            $this->syncImages($request, $product, $syncGallery);

            return $product;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Draft saved. Publish it when it is ready.']);

        return to_route('supplier.products.edit', $product);
    }

    public function edit(Request $request, Product $product): Response
    {
        Gate::authorize('update', $product);

        $product->load(['priceTiers', 'images']);

        return Inertia::render('supplier/products/form', [
            ...$this->formOptions(),
            'product' => [
                'id' => $product->id,
                'slug' => $product->slug,
                'status' => $product->status->value,
                'name' => $product->name,
                'category_id' => $product->category_id,
                'description' => $product->description,
                'unit' => $product->unit->value,
                'moq' => $product->moq,
                'lead_time_min_days' => $product->lead_time_min_days,
                'lead_time_max_days' => $product->lead_time_max_days,
                'currency' => $product->currency,
                'origin_country' => $product->origin_country,
                'sample_available' => $product->sample_available,
                'sample_price' => $product->sample_price !== null ? Money::toMajor($product->sample_price, $product->currency) : '',
                'stock' => $product->stock !== null ? (string) $product->stock : '',
                ...Arr::except($product->stockBadge(), ['stock']),
                'tiers' => $product->priceTiers->map(fn (PriceTier $tier) => [
                    'min_qty' => $tier->min_qty,
                    'unit_price' => Money::toMajor($tier->unit_price, $product->currency),
                ])->values(),
                'images' => $product->images->map(fn (ProductImage $image) => [
                    'id' => $image->id,
                    'url' => $image->url(),
                    'thumbnail_url' => $image->thumbnailUrl(),
                ])->values(),
            ],
            'defaults' => ['currency' => $product->currency],
            'backUrl' => $this->backUrl($request, $product),
        ]);
    }

    public function update(SaveProductRequest $request, Product $product, SaveProduct $saveProduct, SyncGallery $syncGallery): RedirectResponse
    {
        DB::transaction(function () use ($request, $product, $saveProduct, $syncGallery): void {
            $saveProduct->handle($product->company, $product, $request->productAttributes(), $request->tiers());
            $this->syncImages($request, $product, $syncGallery);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Product saved.']);

        return to_route('supplier.products.edit', $product);
    }

    public function updateStatus(Request $request, Product $product, ChangeProductStatus $changeProductStatus): RedirectResponse
    {
        Gate::authorize('update', $product);

        $validated = $request->validate(['status' => ['required', Rule::enum(ProductStatus::class)]]);
        $status = ProductStatus::from($validated['status']);

        $changeProductStatus->handle($product, $status);

        Inertia::flash('toast', ['type' => 'success', 'message' => match ($status) {
            ProductStatus::ACTIVE => "{$product->name} is now listed.",
            ProductStatus::ARCHIVED => "{$product->name} is archived.",
            ProductStatus::DRAFT => "{$product->name} is back to draft.",
        }]);

        return back();
    }

    public function destroy(Request $request, Product $product, DeleteProduct $deleteProduct): RedirectResponse
    {
        Gate::authorize('delete', $product);

        $deleteProduct->handle($product);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Draft deleted.']);

        return redirect($request->user()->currentCompany() ? route('supplier.products.index') : route('home'));
    }

    public function storeImages(Request $request, Product $product, AddProductImages $addProductImages): RedirectResponse
    {
        Gate::authorize('update', $product);

        $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:'.Product::MAX_IMAGES],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'images.*.max' => 'Each photo must be 10 MB or smaller.',
        ]);

        /** @var list<UploadedFile> $uploads */
        $uploads = array_values(Arr::wrap($request->file('images')));
        $addProductImages->handle($product, $uploads);

        return back();
    }

    public function destroyImage(Product $product, ProductImage $image): RedirectResponse
    {
        Gate::authorize('update', $product);
        abort_unless($image->product_id === $product->id, 404);

        Storage::disk('public')->delete([$image->path, $image->thumbnail_path]);
        $image->delete();

        return back();
    }

    /**
     * Where Cancel goes: the seller's product list, or, for staff editing a
     * product they do not own (they have no list), the product's page.
     */
    private function backUrl(Request $request, ?Product $product = null): string
    {
        if ($request->user()->currentCompany() !== null) {
            return route('supplier.products.index');
        }

        return $product ? route('products.show', $product) : route('home');
    }

    private function company(Request $request): Company
    {
        return $request->user()->currentCompany() ?? abort(404);
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * Photos are saved together with the form: new uploads, removals and the
     * featured choice. Files only touch the disk after validation passed.
     */
    private function syncImages(SaveProductRequest $request, Product $product, SyncGallery $syncGallery): void
    {
        $uploads = $request->galleryUploads();
        $removals = $request->galleryRemovals();

        if ($uploads === [] && $removals === [] && $request->galleryCover() === null) {
            return;
        }

        $syncGallery->handle($product->images(), "products/{$product->id}", $uploads, $removals, $request->galleryCover(), Product::MAX_IMAGES);
    }

    private function formOptions(): array
    {
        return [
            'categories' => Category::query()->with('parent:id,name')->ordered()->get()
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'name' => $category->parent ? "{$category->parent->name} › {$category->name}" : $category->name,
                ])->sortBy('name')->values(),
            'units' => collect(ProductUnit::cases())->map(fn (ProductUnit $unit) => ['value' => $unit->value, 'label' => $unit->label()]),
            'currencies' => Money::currencies(),
            'maxTiers' => SaveProductRequest::MAX_TIERS,
            'maxImages' => Product::MAX_IMAGES,
        ];
    }
}
