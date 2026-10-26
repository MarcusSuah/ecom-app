<?php

use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('lists every product on the home page, page by page', function () {
    Product::factory()->count(13)->for(Company::factory()->supplier()->verified())->active()->create();

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->has('products.data', 12)
        ->has('suppliers.data', 1)
        ->has('shops.data', 0)
        ->has('localItems.data', 0));

    $this->get(route('home', ['products' => 2]))->assertInertia(fn (Assert $page) => $page->has('products.data', 1));
});

it('shows suppliers whether or not they are verified or featured', function () {
    Company::factory()->supplier()->create(['name' => 'New Supplier']);

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('suppliers.data.0.name', 'New Supplier'));
});

it('hides suspended businesses and inactive owners from the storefront', function (string $why) {
    $company = Company::factory()->supplier()->verified()->create();
    $product = Product::factory()->for($company)->active()->create();
    match ($why) {
        'suspended' => $company->forceFill(['suspended_at' => now(), 'suspended_reason' => 'Fraud check'])->save(),
        'owner deactivated' => $company->owner->forceFill(['is_active' => false])->save(),
        'owner blocked' => $company->owner->forceFill(['blocked_at' => now()])->save(),
    };

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->has('products.data', 0)
        ->has('suppliers.data', 0));
    $this->get(route('products.show', $product))->assertNotFound();
    $this->get(route('suppliers.show', $company))->assertNotFound();
})->with(['suspended', 'owner deactivated', 'owner blocked']);

it('does not take quote requests above the stock of a listing', function () {
    $product = Product::factory()->for(Company::factory()->supplier()->verified())->active()->create(['moq' => 10, 'stock' => 50]);
    $buyer = User::factory()->create();

    $this->actingAs($buyer)->post(route('inquiries.store'), [
        'items' => [['product_id' => $product->id, 'quantity' => 60]],
        'deliver_city' => 'Kigali',
        'guest_phone' => '0788123456',
    ])->assertSessionHasErrors(['items.0.quantity' => "Only 50 {$product->unit->value} of {$product->name} are in stock."]);

    $this->actingAs($buyer)->post(route('rfqs.store'), [
        'title' => $product->name,
        'category_id' => $product->category_id,
        'product_id' => $product->id,
        'quantity' => 60,
        'unit' => $product->unit->value,
        'deliver_city' => 'Kigali',
        'guest_phone' => '0788123456',
    ])->assertSessionHasErrors('quantity');
});
