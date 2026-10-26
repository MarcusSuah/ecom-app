<?php

use App\Enums\PlanLimit;
use App\Enums\ProductStatus;
use App\Enums\Role;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use Inertia\Testing\AssertableInertia as Assert;

it('lets a supplier create a draft product with its tiers', function () {
    [$owner, $company] = supplierOwner();

    $this->actingAs($owner)
        ->post(route('supplier.products.store'), productPayload())
        ->assertSessionHasNoErrors();

    $product = Product::sole();
    expect($product->company_id)->toBe($company->id)
        ->and($product->status)->toBe(ProductStatus::DRAFT)
        ->and($product->origin_country)->toBe('RW')
        ->and($product->sample_price)->toBe(2000)
        ->and($product->min_unit_price)->toBe(700)
        ->and($product->priceTiers->map->only(['min_qty', 'unit_price'])->all())->toBe([
            ['min_qty' => 500, 'unit_price' => 750],
            ['min_qty' => 2500, 'unit_price' => 700],
        ]);
});

it('shows tier rule errors against the row that breaks them', function () {
    [$owner] = supplierOwner();

    $this->actingAs($owner)
        ->post(route('supplier.products.store'), productPayload([
            'tiers' => [['min_qty' => 500, 'unit_price' => '750'], ['min_qty' => 2500, 'unit_price' => '800']],
        ]))
        ->assertSessionHasErrors(['tiers.1.unit_price' => 'The unit price cannot be higher than the tier before.']);

    expect(Product::count())->toBe(0);
});

it('replaces the tiers when a product is edited', function () {
    [$owner, $company] = supplierOwner();
    $product = Product::factory()->for($company)->create();

    $this->actingAs($owner)
        ->put(route('supplier.products.update', $product), productPayload(['moq' => 10, 'tiers' => [['min_qty' => 10, 'unit_price' => '99.5']], 'currency' => 'USD']))
        ->assertSessionHasNoErrors();

    $product->refresh();
    expect($product->priceTiers)->toHaveCount(1)
        ->and($product->priceTiers->first()->unit_price)->toBe(9950)
        ->and($product->min_unit_price)->toBe(9950);
});

it('does not let buyers or shop owners create products', function (User $user) {
    $this->actingAs($user)
        ->post(route('supplier.products.store'), productPayload())
        ->assertForbidden();

    expect(Product::count())->toBe(0);
})->with([
    'buyer' => [fn () => userWithRole(Role::BUYER)],
    'shop owner' => [fn () => Company::factory()->shop()->create()->owner],
]);

it('asks for a seller plan before publishing when the marketplace has none', function () {
    [$owner, $company] = supplierOwner();
    $draft = Product::factory()->for($company)->create();

    $this->actingAs($owner)
        ->patch(route('supplier.products.status', $draft), ['status' => 'active'])
        ->assertSessionHasErrors(['status' => 'Choose a seller plan before publishing products.']);
});

it('only lets the owning supplier edit a product', function () {
    $product = Product::factory()->create();
    [$otherSupplier] = supplierOwner();

    $this->actingAs($otherSupplier)
        ->put(route('supplier.products.update', $product), productPayload())
        ->assertForbidden();

    $this->actingAs($otherSupplier)
        ->patch(route('supplier.products.status', $product), ['status' => 'active'])
        ->assertForbidden();

    expect($product->fresh()->name)->not->toBe('Premium maize flour');
});

it('hides another marketplace\'s products from the supplier screens', function () {
    $foreign = app(CurrentTenant::class)->runAs(Tenant::factory()->create(), fn () => Product::factory()->create());
    [$owner] = supplierOwner();

    $this->actingAs($owner)
        ->get(route('supplier.products.edit', $foreign))
        ->assertNotFound();
});

it('publishes within the seller plan\'s listed products limit', function (int $alreadyListed, bool $canPublish) {
    Plan::factory()->sellerOf($this->tenant)->default()->limit(PlanLimit::MAX_PRODUCTS, 2)->create();
    [$owner, $company] = supplierOwner();
    Product::factory()->count($alreadyListed)->for($company)->active()->create();
    $draft = Product::factory()->for($company)->create();

    $response = $this->actingAs($owner)->patch(route('supplier.products.status', $draft), ['status' => 'active']);

    $canPublish
        ? $response->assertSessionHasNoErrors()
        : $response->assertSessionHasErrors(['status' => 'Your plan allows 2 listed products. Archive one or upgrade your plan to publish more.']);
    expect($draft->fresh()->status)->toBe($canPublish ? ProductStatus::ACTIVE : ProductStatus::DRAFT);
})->with([
    'room left' => [1, true],
    'limit reached' => [2, false],
]);

it('lets listed products be archived and relisted', function () {
    Plan::factory()->sellerOf($this->tenant)->default()->create();
    [$owner, $company] = supplierOwner();
    $product = Product::factory()->for($company)->active()->create();

    $this->actingAs($owner)->patch(route('supplier.products.status', $product), ['status' => 'archived']);
    expect($product->fresh()->status)->toBe(ProductStatus::ARCHIVED);

    $this->actingAs($owner)->patch(route('supplier.products.status', $product), ['status' => 'active']);
    expect($product->fresh()->status)->toBe(ProductStatus::ACTIVE);
});

it('deletes drafts only', function (ProductStatus $status, bool $deleted) {
    [$owner, $company] = supplierOwner();
    $product = Product::factory()->for($company)->state(['status' => $status])->create();

    $this->actingAs($owner)->delete(route('supplier.products.destroy', $product));

    expect(Product::query()->whereKey($product->id)->exists())->toBe(! $deleted);
})->with([
    'draft' => [ProductStatus::DRAFT, true],
    'listed' => [ProductStatus::ACTIVE, false],
    'archived' => [ProductStatus::ARCHIVED, false],
]);

it('does not delete a category that has products', function () {
    $product = Product::factory()->create();

    $this->actingAs(userWithRole(Role::ADMIN))
        ->delete(route('admin.categories.destroy', $product->category))
        ->assertSessionHasErrors(['category' => 'Products are listed in this category. Move them to another category first.']);
});

it('sends staff who edit a product they do not own back to the product, not to a list they do not have', function () {
    $product = Product::factory()->active()->create();
    $staff = userWithRole(Role::SUPER_ADMIN);

    $this->actingAs($staff)->get(route('supplier.products.edit', $product))->assertInertia(fn (Assert $page) => $page
        ->where('backUrl', route('products.show', $product)));
    $this->actingAs($staff)->get(route('supplier.products.index'))->assertRedirect(route('company.create'));
});

it('sends sellers back to their product list', function () {
    [$owner, $company] = supplierOwner();
    $product = Product::factory()->for($company)->create();

    $this->actingAs($owner)->get(route('supplier.products.edit', $product))->assertInertia(fn (Assert $page) => $page
        ->where('backUrl', route('supplier.products.index')));
});
