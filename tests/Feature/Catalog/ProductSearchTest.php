<?php

use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The slugs of the products the catalogue returns for a query string.
 *
 * @param  array<string, mixed>  $query
 * @return list<string>
 */
function catalogueSlugs(array $query = []): array
{
    $slugs = [];

    test()->get(route('products.index', $query))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$slugs) {
            $slugs = collect($page->toArray()['props']['products']['data'])->pluck('slug')->all();
        });

    return $slugs;
}

it('lists only active products of this marketplace', function () {
    $active = Product::factory()->active()->create();
    Product::factory()->create();
    Product::factory()->archived()->create();
    app(CurrentTenant::class)->runAs(Tenant::factory()->create(), fn () => Product::factory()->active()->create());

    expect(catalogueSlugs())->toBe([$active->slug]);
});

it('finds products by name or description', function () {
    $flour = Product::factory()->active()->create(['name' => 'Maize flour', 'description' => 'Fine milled']);
    $cement = Product::factory()->active()->create(['name' => 'Portland cement', 'description' => 'Grade 42.5 for maize stores']);
    Product::factory()->active()->create(['name' => 'Steel bars', 'description' => 'Deformed']);

    expect(catalogueSlugs(['q' => 'maize']))->toEqualCanonicalizing([$flour->slug, $cement->slug]);
});

it('can show verified suppliers only, while still listing the others by default', function () {
    $verified = Product::factory()->for(Company::factory()->supplier()->verified())->active()->create();
    $pending = Product::factory()->for(Company::factory()->supplier()->pendingVerification())->active()->create();
    $newerRejection = Product::factory()->for(
        Company::factory()->supplier()->verified()->rejectedVerification(),
    )->active()->create();

    expect(catalogueSlugs(['verified' => 1]))->toBe([$verified->slug])
        ->and(catalogueSlugs())->toEqualCanonicalizing([$verified->slug, $pending->slug, $newerRejection->slug]);
});

it('filters by maximum MOQ', function () {
    $small = Product::factory()->active()->create(['moq' => 50]);
    $exact = Product::factory()->active()->create(['moq' => 100]);
    Product::factory()->active()->create(['moq' => 101]);

    expect(catalogueSlugs(['max_moq' => 100]))->toEqualCanonicalizing([$small->slug, $exact->slug]);
});

it('includes sub-categories when filtering by a category', function () {
    $parent = Category::factory()->create();
    $child = Category::factory()->for($parent, 'parent')->create();
    $inParent = Product::factory()->for($parent)->active()->create();
    $inChild = Product::factory()->for($child)->active()->create();
    Product::factory()->active()->create();

    expect(catalogueSlugs(['category' => $parent->slug]))->toEqualCanonicalizing([$inParent->slug, $inChild->slug]);
});

it('filters by lowest unit price within one currency', function () {
    $cheap = Product::factory()->active()->tiers([[100, 500]])->create();
    $inRange = Product::factory()->active()->tiers([[100, 1500], [500, 1200]])->create();
    Product::factory()->active()->tiers([[100, 3000]])->create();
    Product::factory()->active()->tiers([[100, 1000]])->create(['currency' => 'USD']);

    expect(catalogueSlugs(['min_price' => '1000', 'max_price' => '2000', 'currency' => 'RWF']))->toBe([$inRange->slug])
        ->and(catalogueSlugs(['max_price' => '600', 'currency' => 'RWF']))->toBe([$cheap->slug]);
});

it('sorts by lowest price, lowest MOQ and newest', function () {
    $a = Product::factory()->active()->tiers([[300, 900]])->create(['moq' => 300, 'created_at' => now()->subDays(3)]);
    $b = Product::factory()->active()->tiers([[100, 1200]])->create(['moq' => 100, 'created_at' => now()->subDay()]);
    $c = Product::factory()->active()->tiers([[200, 700]])->create(['moq' => 200, 'created_at' => now()->subDays(2)]);

    expect(catalogueSlugs(['sort' => 'price']))->toBe([$c->slug, $a->slug, $b->slug])
        ->and(catalogueSlugs(['sort' => 'moq']))->toBe([$b->slug, $c->slug, $a->slug])
        ->and(catalogueSlugs(['sort' => 'newest']))->toBe([$b->slug, $c->slug, $a->slug]);
});

it('ignores filter values that do not parse', function () {
    $product = Product::factory()->active()->create();

    expect(catalogueSlugs(['max_moq' => 'lots', 'min_price' => 'cheap', 'sort' => 'random', 'category' => 'missing']))
        ->toBe([$product->slug]);
});

it('indexes the supplier\'s verification for external search engines', function () {
    $product = Product::factory()->for(Company::factory()->supplier()->verified())->active()->create();
    config(['scout.driver' => 'meilisearch']);

    expect($product->fresh()->toSearchableArray())
        ->toMatchArray([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
            'supplier_verified' => true,
            'min_unit_price' => 800,
            'moq' => 100,
        ]);
});
