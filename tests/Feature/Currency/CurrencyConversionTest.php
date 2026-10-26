<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\CurrencyRate;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrencyConverter;
use App\Support\CurrentTenant;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    CurrencyRate::factory()->rate('1450')->create();
});

it('converts between currencies, rounding half up to the smallest unit', function (int $amount, string $from, string $to, int $expected) {
    expect(app(CurrencyConverter::class)->convert($amount, $from, $to))->toBe($expected);
})->with([
    'RWF to USD cents' => [1450, 'RWF', 'USD', 100],
    'RWF to USD, rounded' => [1000, 'RWF', 'USD', 69],
    'USD cents to RWF' => [100, 'USD', 'RWF', 1450],
    'half a franc rounds up' => [1, 'USD', 'RWF', 15],
    'same currency' => [12345, 'RWF', 'RWF', 12345],
]);

it('cannot convert without a rate', function () {
    CurrencyRate::query()->delete();

    expect(app(CurrencyConverter::class)->convert(100, 'USD', 'RWF'))->toBeNull()
        ->and(app(CurrencyConverter::class)->label('RWF'))->toBeNull();
});

it('prefers a marketplace\'s own override, for that marketplace only', function () {
    CurrencyRate::factory()->overrideFor($this->tenant)->rate('1500')->create();
    $other = Tenant::factory()->create();

    expect(app(CurrencyConverter::class)->convert(100, 'USD', 'RWF'))->toBe(1500)
        ->and(app(CurrencyConverter::class)->label('RWF'))->toBe('1 USD = 1,500 RWF');

    $otherMarketplaceRate = app(CurrentTenant::class)->runAs($other, function () {
        app(CurrencyConverter::class)->flush();

        return app(CurrencyConverter::class)->convert(100, 'USD', 'RWF');
    });

    expect($otherMarketplaceRate)->toBe(1450);
});

it('fetches the daily rates into the shared rows', function () {
    config(['currency.provider' => 'open_er_api', 'currency.providers.open_er_api.base_url' => 'https://rates.test/v6']);
    Http::fake(['rates.test/v6/latest/USD' => Http::response(['result' => 'success', 'rates' => ['USD' => 1, 'RWF' => 1462.25]])]);

    $this->artisan('currency:fetch-rates')->assertSuccessful();

    expect(CurrencyRate::query()->shared()->sole())
        ->rate->toBe('1462.25000000')
        ->source->toBe('open_er_api');
});

it('keeps the previous rates when the fetch fails', function () {
    config(['currency.provider' => 'open_er_api', 'currency.providers.open_er_api.base_url' => 'https://rates.test/v6']);
    Http::fake(['rates.test/*' => Http::response(['result' => 'error', 'error-type' => 'unsupported-code'])]);

    $this->artisan('currency:fetch-rates')->assertFailed();

    expect(CurrencyRate::query()->shared()->sole()->rate)->toBe('1450.00000000');
});

it('remembers the chosen currency for guests and on the account', function () {
    $this->put(route('currency.update'), ['currency' => 'USD'])->assertSessionHasNoErrors();
    $this->get(route('products.index'))->assertInertia(fn (Assert $page) => $page->where('currency.display', 'USD'));

    $member = User::factory()->create();
    $this->actingAs($member)->put(route('currency.update'), ['currency' => 'USD']);
    expect($member->fresh()->preferred_currency)->toBe('USD');

    $this->put(route('currency.update'), ['currency' => 'EUR'])->assertSessionHasErrors('currency');
});

it('shows prices in the chosen currency, with the price charged, and the rate in the footer', function () {
    $product = Product::factory()->for(Company::factory()->supplier()->verified())->active()->create();

    $this->get(route('products.index'))->assertInertia(fn (Assert $page) => $page
        ->where('currency.display', 'RWF')
        ->where('currency.rate_label', '1 USD = 1,450 RWF')
        ->where('products.data.0.from_price', 'RWF 800')
        ->where('products.data.0.from_price_listed', null));

    $this->put(route('currency.update'), ['currency' => 'USD']);

    $this->get(route('products.index'))->assertInertia(fn (Assert $page) => $page
        ->where('products.data.0.from_price', 'USD 0.55')
        ->where('products.data.0.from_price_listed', 'RWF 800')
        ->where('currency.rates.RWF', fn ($rate) => abs($rate - 1 / 1450) < 1e-9));
    $this->get(route('products.show', $product))->assertInertia(fn (Assert $page) => $page
        ->where('product.tiers.0.formatted_unit_price', 'USD 0.69')
        ->where('product.tiers.0.listed_unit_price', 'RWF 1,000')
        ->where('product.display.currency', 'USD'));
});

it('lets marketplace admins override the rate and go back to the daily one', function () {
    $admin = userWithRole(Role::ADMIN);

    $this->actingAs($admin)->put(route('admin.currency.update'), ['quote' => 'RWF', 'rate' => '1480.5'])->assertSessionHasNoErrors();
    expect(CurrencyRate::query()->where('tenant_id', $this->tenant->id)->sole())->rate->toBe('1480.50000000')->source->toBe('manual');

    $this->actingAs($admin)->put(route('admin.currency.update'), ['quote' => 'RWF', 'rate' => '0'])->assertSessionHasErrors('rate');
    $this->actingAs($admin)->put(route('admin.currency.update'), ['quote' => 'USD', 'rate' => '2'])->assertSessionHasErrors('quote');

    $this->actingAs($admin)->delete(route('admin.currency.destroy', 'RWF'));
    expect(CurrencyRate::query()->where('tenant_id', $this->tenant->id)->exists())->toBeFalse()
        ->and(CurrencyRate::query()->shared()->exists())->toBeTrue();

    $this->actingAs(userWithRole(Role::VERIFIER))->put(route('admin.currency.update'), ['quote' => 'RWF', 'rate' => '1'])->assertForbidden();
});
