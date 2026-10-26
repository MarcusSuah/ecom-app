<?php

use App\Models\Company;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use Inertia\Testing\AssertableInertia as Assert;

it('serves every public page to guests', function (string $url) {
    $this->get($url)->assertOk();
})->with([
    'home' => fn () => route('home'),
    'products' => fn () => route('products.index'),
    'categories' => fn () => route('categories.index'),
    'suppliers' => fn () => route('suppliers.index'),
    'local shops' => fn () => route('shops.index'),
    'request quotes' => fn () => route('rfqs.create'),
    'contact' => fn () => route('contact.create'),
    'list your products' => fn () => route('pages.show', 'sell'),
    'get verified' => fn () => route('pages.show', 'get-verified'),
    'seller guide' => fn () => route('pages.show', 'seller-guide'),
    'payment protection' => fn () => route('pages.show', 'payment-protection'),
    'shipping and customs' => fn () => route('pages.show', 'shipping-and-customs'),
    'sitemap' => fn () => route('sitemap'),
    'robots' => fn () => route('robots'),
]);

it('serves product, supplier and shop pages to guests', function () {
    $product = Product::factory()->for(Company::factory()->supplier()->verified())->active()->create();
    $shop = Shop::factory()->create();

    $this->get(route('products.show', $product))->assertOk();
    $this->get(route('suppliers.show', $product->company))->assertOk();
    $this->get(route('shops.show', $shop->company))->assertOk();
});

it('renders each content page with the marketplace\'s real figures', function () {
    config(['payments.auto_release_days' => 5, 'payments.protection_label' => 'Escrow']);

    $this->get(route('pages.show', 'payment-protection'))->assertInertia(fn (Assert $page) => $page
        ->component('content/payment-protection')
        ->where('title', 'Escrow')
        ->where('facts.autoReleaseDays', 5)
        ->where('meta.title', 'Escrow'));

    $this->get('/not-a-content-page')->assertNotFound();
});

it('gives pages search and link-preview tags without JavaScript', function () {
    // The root view's fallback tags, as served when no SSR server runs.
    config(['inertia.ssr.enabled' => false]);
    $product = Product::factory()->for(Company::factory()->supplier()->verified())->active()->create(['name' => 'Arabica coffee beans']);

    $this->get(route('products.show', $product))
        ->assertInertia(fn (Assert $page) => $page
            ->where('meta.title', 'Arabica coffee beans')
            ->where('meta.type', 'product')
            ->where('meta.url', route('products.show', $product)))
        ->assertSee('<meta data-inertia="og:title" property="og:title" content="Arabica coffee beans">', false)
        ->assertSee('<title>Arabica coffee beans - '.$this->tenant->name.'</title>', false);
});

it('lists only this marketplace\'s public pages in the sitemap', function () {
    $listed = Product::factory()->active()->create();
    $draft = Product::factory()->create();
    $foreign = app(CurrentTenant::class)->runAs(Tenant::factory()->create(), fn () => Product::factory()->active()->create());

    $this->get(route('sitemap'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<loc>'.route('products.show', $listed).'</loc>', false)
        ->assertSee('<loc>'.route('pages.show', 'payment-protection').'</loc>', false)
        ->assertDontSee(route('products.show', $draft), false)
        ->assertDontSee($foreign->slug, false);

    $this->get(route('robots'))->assertSee('Sitemap: '.route('sitemap'))->assertSee('Disallow: /admin');
});

it('shows a branded error page when debug output is off', function () {
    config(['app.debug' => false]);

    $this->get('/no-such-page')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('error')
            ->where('status', 404)
            ->where('marketplace', $this->tenant->name));

    $this->getJson('/api/no-such-endpoint')->assertNotFound()->assertJsonStructure(['message']);
});
