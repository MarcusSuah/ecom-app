<?php

use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shows a listed product with its tiers to guests', function () {
    $product = Product::factory()->for(Company::factory()->supplier()->verified())->active()->create();

    $this->get(route('products.show', $product))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/products/show')
            ->where('product.moq', 100)
            ->where('product.tiers.0.formatted_unit_price', 'RWF 1,000')
            ->where('product.tiers.2.min_qty', 1000)
            ->where('supplier.is_verified', true)
            ->where('canEdit', false));
});

it('hides unlisted products from everyone but their supplier', function () {
    $owner = User::factory()->create();
    $draft = Product::factory()->for(Company::factory()->for($owner, 'owner')->supplier())->create();

    $this->get(route('products.show', $draft))->assertNotFound();
    $this->actingAs(User::factory()->create())->get(route('products.show', $draft))->assertNotFound();
    $this->actingAs($owner)->get(route('products.show', $draft))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canEdit', true));
});

it('counts active products per category, including sub-categories', function () {
    $parent = Category::factory()->create(['name' => 'Agriculture']);
    $child = Category::factory()->for($parent, 'parent')->create(['name' => 'Seeds']);
    Product::factory()->for($parent)->active()->create();
    Product::factory()->count(2)->for($child)->active()->create();
    Product::factory()->for($child)->create();

    $this->get(route('categories.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/categories')
            ->has('categories', 1)
            ->where('categories.0.product_count', 3)
            ->where('categories.0.children.0.product_count', 2));
});

it('shows a supplier profile with top categories and listed products', function () {
    $supplier = Company::factory()->supplier()->verified()->create();
    $food = Category::factory()->create(['name' => 'Food']);
    $farming = Category::factory()->create(['name' => 'Farming']);
    Product::factory()->count(2)->for($supplier)->for($food)->active()->create();
    Product::factory()->for($supplier)->for($farming)->active()->create();
    Product::factory()->for($supplier)->for($farming)->create();

    $this->get(route('suppliers.show', $supplier))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/suppliers/show')
            ->where('supplier.is_verified', true)
            ->where('supplier.response_time', null)
            ->where('topCategories.0.name', 'Food')
            ->where('topCategories.0.product_count', 2)
            ->where('products.total', 3));
});

it('does not show shops as suppliers', function () {
    $shop = Company::factory()->shop()->create();

    $this->get(route('suppliers.show', $shop))->assertNotFound();
});

it('lists suppliers with featured ones first and a verified filter', function () {
    $plain = Company::factory()->supplier()->create(['name' => 'Alpha Supplies']);
    $featured = Company::factory()->supplier()->verified()->featured()->create(['name' => 'Zulu Traders']);
    Company::factory()->shop()->create();

    $this->get(route('suppliers.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('suppliers.data', 2)
            ->where('suppliers.data.0.id', $featured->id)
            ->where('suppliers.data.1.id', $plain->id));

    $this->get(route('suppliers.index', ['verified' => 1]))
        ->assertInertia(fn (Assert $page) => $page->has('suppliers.data', 1)->where('suppliers.data.0.id', $featured->id));
});

it('renders the supplier product screens', function () {
    $owner = User::factory()->create();
    $product = Product::factory()->for(Company::factory()->for($owner, 'owner')->supplier())->create();

    $this->actingAs($owner)->get(route('supplier.products.index'))->assertInertia(fn (Assert $page) => $page->component('supplier/products/index'));
    $this->actingAs($owner)->get(route('supplier.products.create'))->assertInertia(fn (Assert $page) => $page->component('supplier/products/form'));
    $this->actingAs($owner)->get(route('supplier.products.edit', $product))
        ->assertInertia(fn (Assert $page) => $page->component('supplier/products/form')->where('product.tiers.0.unit_price', '1000'));
});

it('passes the product and quantity from the product page to the quote request form', function () {
    $product = Product::factory()->active()->create();

    $this->get(route('rfqs.create', ['product' => $product->id, 'quantity' => 250]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/request-quote')
            ->where('product.slug', $product->slug)
            ->where('defaults.quantity', '250'));
});
