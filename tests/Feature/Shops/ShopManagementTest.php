<?php

use App\Enums\PlanLimit;
use App\Models\Address;
use App\Models\Category;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\ShopProduct;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function settingsPayload(array $overrides = []): array
{
    return [
        'lat' => -1.9496, 'lng' => 30.1260,
        'address_text' => 'KG 11 Ave, Kimironko', 'landmark' => 'Opposite the market',
        'offers_pickup' => true, 'offers_delivery' => true,
        'currency' => 'RWF', 'base_fee' => '1000', 'per_km_rate' => '300', 'free_threshold' => '20000',
        'delivery_radius_km' => '6',
        'opening_hours' => [
            'mon' => ['open' => '08:00', 'close' => '20:00'],
            'sun' => null,
        ],
        ...$overrides,
    ];
}

it('saves a shop\'s location, fees and opening hours', function () {
    $company = Company::factory()->shop()->create();

    $this->actingAs($company->owner)->put(route('shop.settings.update'), settingsPayload())->assertSessionHasNoErrors();

    $shop = $company->shop()->sole();
    expect($shop)
        ->base_fee->toBe(1000)
        ->per_km_rate->toBe(300)
        ->free_threshold->toBe(20000)
        ->address_text->toBe('KG 11 Ave, Kimironko')
        ->and((float) $shop->lat)->toBe(-1.9496)
        ->and($shop->opening_hours['mon'])->toBe(['open' => '08:00', 'close' => '20:00'])
        ->and($shop->opening_hours['sun'])->toBeNull()
        ->and($shop->opening_hours['tue'])->toBeNull();
});

it('requires pickup, delivery or both', function () {
    $company = Company::factory()->shop()->create();

    $this->actingAs($company->owner)
        ->put(route('shop.settings.update'), settingsPayload(['offers_pickup' => false, 'offers_delivery' => false]))
        ->assertSessionHasErrors(['offers_pickup' => 'Offer pickup, delivery or both.']);
});

it('only lets shop companies set delivery settings', function () {
    $supplier = Company::factory()->supplier()->create();

    $this->actingAs($supplier->owner)->put(route('shop.settings.update'), settingsPayload())->assertForbidden();
});

it('lets a shop add items with a resized photo within its plan', function () {
    Storage::fake('public');
    Plan::factory()->sellerOf($this->tenant)->default()->create();
    $shop = Shop::factory()->create();

    $this->actingAs($shop->company->owner)->post(route('shop.products.store'), [
        'name' => 'Milk, 1 L', 'category_id' => Category::factory()->create()->id,
        'price' => '900', 'stock' => 20, 'is_active' => true,
        'images' => [UploadedFile::fake()->image('milk.jpg', 1000, 800), UploadedFile::fake()->image('back.jpg', 800, 800)],
        'cover' => 'new:1',
    ])->assertSessionHasNoErrors();

    $item = ShopProduct::sole();
    expect($item)->price->toBe(900)->stock->toBe(20)->is_active->toBeTrue()
        ->and($item->images)->toHaveCount(2);
    // The chosen photo is featured: first in the gallery, and its thumbnail is the item's image.
    expect($item->images->first()->position)->toBe(0)
        ->and($item->image)->toBe($item->images->first()->thumbnail_path);
    Storage::disk('public')->assertExists([$item->image, $item->images->first()->path]);
    expect(getimagesizefromstring(Storage::disk('public')->get($item->image))[0])->toBe(400);
});

it('lets a shop remove photos and change the featured one', function () {
    Storage::fake('public');
    $shop = Shop::factory()->create();
    $item = ShopProduct::factory()->for($shop)->create();
    $owner = $shop->company->owner;
    $payload = ['name' => $item->name, 'category_id' => $item->category_id, 'price' => '900', 'stock' => 5, 'is_active' => false];

    $this->actingAs($owner)->post(route('shop.products.update', $item), [...$payload, 'images' => [
        UploadedFile::fake()->image('a.jpg', 400, 400), UploadedFile::fake()->image('b.jpg', 400, 400),
    ]])->assertSessionHasNoErrors();
    [$first, $second] = $item->images()->get()->all();

    $this->actingAs($owner)->post(route('shop.products.update', $item), [...$payload, 'remove_images' => [$first->id], 'cover' => "existing:{$second->id}"])
        ->assertSessionHasNoErrors();

    expect($item->fresh()->images->pluck('id')->all())->toBe([$second->id])
        ->and($item->fresh()->image)->toBe($second->thumbnail_path);
    Storage::disk('public')->assertMissing($first->path);
});

it('counts items for sale against the plan\'s listed products limit', function () {
    Plan::factory()->sellerOf($this->tenant)->default()->limit(PlanLimit::MAX_PRODUCTS, 1)->create();
    $shop = Shop::factory()->create();
    ShopProduct::factory()->for($shop)->create();
    $payload = ['name' => 'Bread', 'category_id' => Category::factory()->create()->id, 'price' => '1200', 'stock' => 5];

    $this->actingAs($shop->company->owner)->post(route('shop.products.store'), [...$payload, 'is_active' => true])
        ->assertSessionHasErrors(['is_active' => 'Your plan allows 1 listed items. Hide one or upgrade your plan.']);
    $this->actingAs($shop->company->owner)->post(route('shop.products.store'), [...$payload, 'is_active' => false])
        ->assertSessionHasNoErrors();
});

it('only lets the owning shop edit an item', function () {
    $item = ShopProduct::factory()->create();
    $otherShop = Shop::factory()->create();

    $this->actingAs($otherShop->company->owner)
        ->post(route('shop.products.update', $item), ['name' => 'Hijacked', 'category_id' => $item->category_id, 'price' => '1', 'stock' => 1])
        ->assertForbidden();

    expect($item->fresh()->name)->not->toBe('Hijacked');
});

it('keeps saved addresses private to their owner', function () {
    $address = Address::factory()->create();
    $someoneElse = User::factory()->create();

    $this->actingAs($someoneElse)->delete(route('addresses.destroy', $address))->assertForbidden();
    $this->actingAs($someoneElse)->post(route('addresses.use', $address))->assertForbidden();

    $this->actingAs($address->user)->get(route('addresses.index'))->assertInertia(fn (Assert $page) => $page
        ->component('addresses/index')
        ->has('addresses', 1));
});

it('makes the first saved address the default', function () {
    $member = User::factory()->create();

    $this->actingAs($member)->post(route('addresses.store'), ['label' => 'Home', 'lat' => -1.95, 'lng' => 30.1])->assertSessionHasNoErrors();
    $this->actingAs($member)->post(route('addresses.store'), ['label' => 'Office', 'lat' => -1.94, 'lng' => 30.06])->assertSessionHasNoErrors();

    expect(Address::query()->where('is_default', true)->sole()->label)->toBe('Home');
});

it('renders the shop owner screens', function () {
    $shop = Shop::factory()->create();
    $item = ShopProduct::factory()->for($shop)->create();
    $owner = $shop->company->owner;

    $this->actingAs($owner)->get(route('shop.settings.edit'))->assertInertia(fn (Assert $page) => $page->component('shop/settings')->where('shop.base_fee', '1000'));
    $this->actingAs($owner)->get(route('shop.products.index'))->assertInertia(fn (Assert $page) => $page->component('shop/products/index'));
    $this->actingAs($owner)->get(route('shop.products.edit', $item))->assertInertia(fn (Assert $page) => $page->component('shop/products/form'));
    $this->actingAs($owner)->get(route('seller.orders.index'))->assertInertia(fn (Assert $page) => $page->component('seller/orders/index'));
});
