<?php

use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

it('shows live stats that reflect the database', function () {
    $verified = Company::factory()->supplier()->verified()->create();
    Company::factory()->supplier()->pendingVerification()->create();
    Company::factory()->shop()->verified()->create();
    Product::factory()->for($verified)->active()->create(['origin_country' => 'RW']);
    Product::factory()->for($verified)->active()->create(['origin_country' => 'KE']);
    Product::factory()->for($verified)->active()->create(['origin_country' => 'KE']);
    Product::factory()->for($verified)->create(['origin_country' => 'CN']);

    app(CurrentTenant::class)->runAs(Tenant::factory()->create(), fn () => Product::factory()->for(Company::factory()->supplier()->verified())->active()->create(['origin_country' => 'UG']));

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->where('stats', ['verified_suppliers' => 1, 'listed_products' => 3, 'countries' => 2]));
});

it('caches the stats for a few minutes', function () {
    $this->get(route('home'));
    Product::factory()->active()->create();

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('stats.listed_products', 0));

    Cache::flush();
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('stats.listed_products', 1));
});

it('lists categories, products, featured suppliers and the request form', function () {
    $food = Category::factory()->create(['name' => 'Food', 'sort_order' => 2]);
    $farming = Category::factory()->create(['name' => 'Farming', 'sort_order' => 1]);
    $featured = Company::factory()->supplier()->featured()->create(['name' => 'Akagera Foods']);
    Product::factory()->count(2)->for($featured)->for($food)->active()->create();
    Product::factory()->for($featured)->for($farming)->active()->create();

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->has('categories', 2)
        ->where('categories.0.name', 'Farming')
        ->where('categories.0.product_count', 1)
        ->where('categories.1.product_count', 2)
        ->has('products.data', 3)
        ->has('suppliers.data', 1)
        ->where('suppliers.data.0.name', 'Akagera Foods')
        ->where('suppliers.data.0.top_categories', ['Food', 'Farming'])
        ->has('rfqForm.categories', 2)
        ->has('rfqForm.units'));
});

it('filters the products section to verified suppliers with a maximum MOQ', function () {
    Product::factory()->for(Company::factory()->supplier()->verified())->active()->create(['name' => 'Verified small', 'moq' => 50]);
    Product::factory()->for(Company::factory()->supplier()->verified())->active()->create(['name' => 'Verified large', 'moq' => 5000]);
    Product::factory()->for(Company::factory()->supplier())->active()->create(['name' => 'Unverified', 'moq' => 50]);

    $this->get(route('home', ['verified' => 1, 'max_moq' => 100]))->assertInertia(fn (Assert $page) => $page
        ->has('products.data', 1)
        ->where('products.data.0.name', 'Verified small')
        ->where('productFilters', ['verified' => true, 'max_moq' => '100', 'sort' => 'relevance', 'category' => '']));
});

it('only filters shops by delivery once a location is set', function () {
    $this->get(route('home', ['delivers' => 1]))->assertInertia(fn (Assert $page) => $page->where('shopFilters.delivers', false));
});

it('pre-fills the request form from the quick quote card', function () {
    $this->get(route('rfqs.create', ['title' => 'Cement, 50 kg bags', 'quantity' => 500]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/request-quote')
            ->where('defaults.title', 'Cement, 50 kg bags')
            ->where('defaults.quantity', '500'));
});
