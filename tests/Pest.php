<?php

use App\Enums\Role;
use App\Models\Category;
use App\Models\Company;
use App\Models\Shop;
use App\Models\ShopProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * A user holding a role on the current marketplace.
 */
function userWithRole(Role $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

/**
 * A supplier company and its owner.
 *
 * @return array{0: User, 1: Company}
 */
function supplierOwner(): array
{
    $owner = User::factory()->create();
    $company = Company::factory()->for($owner, 'owner')->supplier()->create();

    return [$owner, $company];
}

/**
 * A valid supplier product form.
 *
 * @return array<string, mixed>
 */
function productPayload(array $overrides = []): array
{
    return [
        'name' => 'Premium maize flour',
        'category_id' => Category::factory()->create()->id,
        'description' => 'Fine milled.',
        'unit' => 'kg',
        'moq' => 500,
        'lead_time_min_days' => 3,
        'lead_time_max_days' => 10,
        'currency' => 'RWF',
        'origin_country' => 'rw',
        'sample_available' => true,
        'sample_price' => '2000',
        'tiers' => [
            ['min_qty' => 500, 'unit_price' => '750'],
            ['min_qty' => 2500, 'unit_price' => '700'],
        ],
        ...$overrides,
    ];
}

/**
 * A shop $km north of the customer at the city centre, with one item.
 *
 * @return array{0: Shop, 1: ShopProduct}
 */
function shopWithItem(float $km = 2.0, int $price = 2500, int $stock = 10, array $shopAttributes = []): array
{
    $shop = Shop::factory()->at(-1.9441 + $km / (6371 * M_PI / 180), 30.0619)->create([
        'base_fee' => 1000, 'per_km_rate' => 300, 'free_threshold' => 20000, ...$shopAttributes,
    ]);

    return [$shop, ShopProduct::factory()->for($shop)->create(['price' => $price, 'stock' => $stock])];
}

function customerAtCityCentre(): User
{
    $customer = User::factory()->create();
    test()->actingAs($customer)->put(route('location.update'), ['label' => 'Office', 'lat' => -1.9441, 'lng' => 30.0619, 'landmark' => 'Near the roundabout']);

    return $customer;
}
