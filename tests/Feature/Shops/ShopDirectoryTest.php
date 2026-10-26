<?php

use App\Models\Address;
use App\Models\Category;
use App\Models\Company;
use App\Models\Shop;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use App\Support\CustomerLocation;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

const CITY_CENTRE = ['lat' => -1.9441, 'lng' => 30.0619];

function shopAt(string $name, float $kmNorthOfCentre, array $attributes = []): Shop
{
    return Shop::factory()
        ->for(Company::factory()->shop()->state(['name' => $name]))
        ->at(CITY_CENTRE['lat'] + $kmNorthOfCentre / (6371 * M_PI / 180), CITY_CENTRE['lng'])
        ->create($attributes);
}

function setLocation(array $location = CITY_CENTRE): void
{
    test()->put(route('location.update'), [...$location, 'label' => 'City Centre'])->assertSessionHasNoErrors();
}

/**
 * @return list<string>
 */
function listedShopNames(array $query = []): array
{
    $names = [];

    test()->get(route('shops.index', $query))->assertOk()->assertInertia(function (Assert $page) use (&$names) {
        $names = collect($page->toArray()['props']['shops']['data'])->pluck('name')->all();
    });

    return $names;
}

it('lists shops nearest first from the chosen location', function () {
    config(['marketplace.road_factor' => 1.0]);
    shopAt('Far Shop', 3.0);
    shopAt('Near Shop', 0.5);
    shopAt('Middle Shop', 1.5);
    setLocation();

    expect(listedShopNames())->toBe(['Near Shop', 'Middle Shop', 'Far Shop']);
});

it('only shows shops that deliver to the area when asked', function () {
    config(['marketplace.road_factor' => 1.0]);
    shopAt('In Range', 2.0, ['delivery_radius_km' => 3]);
    shopAt('Out Of Range', 4.0, ['delivery_radius_km' => 3]);
    shopAt('Pickup Only', 0.5, ['offers_delivery' => false]);
    setLocation();

    expect(listedShopNames(['delivers' => 1]))->toBe(['In Range'])
        ->and(listedShopNames())->toBe(['Pickup Only', 'In Range', 'Out Of Range']);
});

it('shows the distance, delivery estimate and open status on each card', function () {
    config(['marketplace.road_factor' => 1.0]);
    Carbon::setTestNow(Carbon::parse('2026-10-19 10:00', 'Africa/Kigali'));
    shopAt('Open Shop', 2.0, ['base_fee' => 1000, 'per_km_rate' => 300, 'free_threshold' => 20000]);
    setLocation();

    $this->get(route('shops.index'))->assertInertia(fn (Assert $page) => $page
        ->component('catalog/shops/index')
        ->where('shops.data.0.delivery.distance_km', 2)
        ->where('shops.data.0.delivery.formatted_fee', 'RWF 1,300')
        ->where('shops.data.0.free_threshold', 'RWF 20,000')
        ->where('shops.data.0.is_open', true));
});

it('reads opening hours in Kigali time, including closed days', function () {
    $shop = Shop::factory()->create(['opening_hours' => [
        'mon' => ['open' => '08:00', 'close' => '20:00'],
        'sun' => null,
    ]]);

    expect($shop->isOpenAt(Carbon::parse('2026-10-19 07:59', 'Africa/Kigali')))->toBeFalse()
        ->and($shop->isOpenAt(Carbon::parse('2026-10-19 08:00', 'Africa/Kigali')))->toBeTrue()
        ->and($shop->isOpenAt(Carbon::parse('2026-10-19 19:59', 'Africa/Kigali')))->toBeTrue()
        ->and($shop->isOpenAt(Carbon::parse('2026-10-19 20:00', 'Africa/Kigali')))->toBeFalse()
        ->and($shop->isOpenAt(Carbon::parse('2026-10-18 12:00', 'Africa/Kigali')))->toBeFalse();
});

it('uses a member\'s default saved address when no location was picked', function () {
    config(['marketplace.road_factor' => 1.0]);
    shopAt('Shop', 0.0);
    $member = User::factory()->create();
    Address::factory()->for($member)->create(['label' => 'Home', 'lat' => CITY_CENTRE['lat'], 'lng' => CITY_CENTRE['lng'], 'is_default' => true]);

    $this->actingAs($member)->get(route('shops.index'))->assertInertia(fn (Assert $page) => $page
        ->where('location.label', 'Home')
        ->where('shops.data.0.delivery.distance_km', 0));
});

it('keeps the picked location per marketplace', function () {
    setLocation();
    $other = Tenant::factory()->create();

    app(CurrentTenant::class)->runAs($other, function () {
        expect(CustomerLocation::get(request()))->toBeNull();
    });
});

it('validates a picked location', function () {
    $this->put(route('location.update'), ['label' => '', 'lat' => 120, 'lng' => 'east'])
        ->assertSessionHasErrors(['label', 'lat', 'lng']);
});

it('shows a shop page with its available items and the delivery estimate', function () {
    config(['marketplace.road_factor' => 1.0]);
    $shop = shopAt('Corner Shop', 1.0);
    $shop->products()->create(['category_id' => Category::factory()->create()->id, 'name' => 'Milk', 'price' => 900, 'stock' => 5, 'is_active' => true]);
    $shop->products()->create(['category_id' => Category::factory()->create()->id, 'name' => 'Hidden', 'price' => 900, 'stock' => 5, 'is_active' => false]);
    setLocation();

    $this->get(route('shops.show', $shop->company))->assertInertia(fn (Assert $page) => $page
        ->component('catalog/shops/show')
        ->has('products.data', 1)
        ->where('products.data.0.name', 'Milk')
        ->where('shop.delivery.formatted_fee', 'RWF 1,000'));
});

it('does not show suppliers as shops', function () {
    $this->get(route('shops.show', Company::factory()->supplier()->create()))->assertNotFound();
});
