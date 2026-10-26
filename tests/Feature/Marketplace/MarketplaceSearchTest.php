<?php

use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopProduct;
use Inertia\Testing\AssertableInertia as Assert;

it('searches products, shop items, shops, suppliers and categories at once', function () {
    $category = Category::factory()->create(['name' => 'Coffee and tea']);
    Product::factory()->for(Company::factory()->supplier()->verified())->for($category)->active()->create(['name' => 'Arabica green beans']);
    $shop = Shop::factory()->create();
    $shop->company->update(['name' => 'Bean Corner']);
    ShopProduct::factory()->for($shop)->create(['name' => 'Espresso beans 500 g']);
    Company::factory()->supplier()->create(['name' => 'Beans of Rwanda']);
    Category::factory()->create(['name' => 'Beans and pulses']);

    $this->get(route('search', ['q' => 'bean']))->assertInertia(fn (Assert $page) => $page
        ->component('catalog/search')
        ->has('results.products', 1)
        ->has('results.items', 1)
        ->has('results.shops', 1)
        ->where('results.shops.0.name', 'Bean Corner')
        ->has('results.suppliers', 1)
        ->where('results.categories.0.name', 'Beans and pulses')
        ->where('results.total', 5));
});

it('suggests matches while typing, leaving out suspended businesses', function () {
    $hidden = Company::factory()->supplier()->create(['name' => 'Maize Mills', 'suspended_at' => now()]);
    Product::factory()->for($hidden)->active()->create(['name' => 'Maize flour hidden']);
    Product::factory()->for(Company::factory()->supplier())->active()->create(['name' => 'Maize flour 25 kg']);

    $this->getJson(route('search.suggest', ['q' => 'maize']))
        ->assertOk()
        ->assertJsonCount(1, 'products')
        ->assertJsonPath('products.0.name', 'Maize flour 25 kg')
        ->assertJsonCount(0, 'suppliers');
});

it('waits for at least two letters', function () {
    Product::factory()->active()->create(['name' => 'X-ray film']);

    $this->getJson(route('search.suggest', ['q' => 'x']))->assertJsonPath('total', 0);
});
