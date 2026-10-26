<?php

use App\Actions\Rfqs\MatchSuppliersForRfq;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\Rfq;
use App\Models\Tenant;
use App\Support\CurrentTenant;

/**
 * A verified supplier with one active product in $category at $moq.
 */
function supplierListing(Category $category, int $moq = 100, string $verification = 'verified'): Company
{
    $supplier = Company::factory()->supplier()->{$verification}()->create();
    Product::factory()->for($supplier)->for($category)->active()->create(['moq' => $moq]);

    return $supplier;
}

/**
 * @return list<int>
 */
function matchedIds(Rfq $rfq): array
{
    return app(MatchSuppliersForRfq::class)->handle($rfq)->modelKeys();
}

it('matches verified suppliers listing in the category with an MOQ that fits', function () {
    $category = Category::factory()->create();
    $fits = supplierListing($category, moq: 500);
    $exact = supplierListing($category, moq: 1000);
    supplierListing($category, moq: 1001);
    supplierListing($category, verification: 'pendingVerification');
    supplierListing(Category::factory()->create());

    $rfq = Rfq::factory()->for($category)->create(['quantity' => 1000]);

    expect(matchedIds($rfq))->toEqualCanonicalizing([$fits->id, $exact->id]);
});

it('ignores products that are not listed', function () {
    $category = Category::factory()->create();
    $supplier = Company::factory()->supplier()->verified()->create();
    Product::factory()->for($supplier)->for($category)->create(['moq' => 10]);

    expect(matchedIds(Rfq::factory()->for($category)->create()))->toBe([]);
});

it('includes suppliers listing in a sub-category', function () {
    $parent = Category::factory()->create();
    $child = Category::factory()->for($parent, 'parent')->create();
    $supplier = supplierListing($child);

    expect(matchedIds(Rfq::factory()->for($parent)->create()))->toBe([$supplier->id]);
});

it('sends to at most 20 suppliers', function () {
    $category = Category::factory()->create();
    foreach (range(1, 22) as $ignored) {
        supplierListing($category);
    }

    expect(matchedIds(Rfq::factory()->for($category)->create()))->toHaveCount(20);
});

it('always includes the verified supplier of the product the request started from', function () {
    $category = Category::factory()->create();
    foreach (range(1, 20) as $ignored) {
        supplierListing($category);
    }
    $origin = Company::factory()->supplier()->verified()->create();
    // Its MOQ is above the requested quantity, but it is still asked.
    $product = Product::factory()->for($origin)->for($category)->active()->create(['moq' => 5000]);

    $ids = matchedIds(Rfq::factory()->for($category)->for($product)->create(['quantity' => 1000]));

    expect($ids)->toHaveCount(20)->and($ids[0])->toBe($origin->id);
});

it('does not include the product supplier when it is not verified', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->for(Company::factory()->supplier()->pendingVerification())->for($category)->active()->create();

    expect(matchedIds(Rfq::factory()->for($category)->for($product)->create()))->toBe([]);
});

it('never sends a buyer\'s request to their own company or to other marketplaces', function () {
    $category = Category::factory()->create();
    $ownCompany = supplierListing($category);
    app(CurrentTenant::class)->runAs(Tenant::factory()->create(), function () {
        supplierListing(Category::factory()->create());
    });

    $rfq = Rfq::factory()->for($category)->for($ownCompany->owner, 'buyer')->create();

    expect(matchedIds($rfq))->toBe([]);
});
