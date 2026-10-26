<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Verification;
use App\Support\CurrentTenant;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Builds a record on another, open marketplace and switches back.
 *
 * @template T
 *
 * @param  callable(Tenant): T  $callback
 * @return T
 */
function onAnotherMarketplace(callable $callback): mixed
{
    $other = Tenant::factory()->create();
    Subscription::factory()->forSubscriber($other, Plan::factory()->create())->create();

    return app(CurrentTenant::class)->runAs($other, fn () => $callback($other));
}

it('resolves the marketplace from its subdomain and its custom domain', function () {
    $other = onAnotherMarketplace(fn (Tenant $tenant) => tap($tenant)->update(['domain' => 'shop.example.com']));

    $this->get('http://'.$other->slug.'.'.config('tenancy.central_domain').'/')
        ->assertInertia(fn (Assert $page) => $page->where('tenant.id', $other->id));

    $this->get('http://shop.example.com/')
        ->assertInertia(fn (Assert $page) => $page->where('tenant.id', $other->id));
});

it('returns not found for an unknown marketplace subdomain', function () {
    $this->get('http://missing.'.config('tenancy.central_domain').'/')->assertNotFound();
});

it('hides other marketplaces\' records from route model binding', function () {
    $foreignVerification = onAnotherMarketplace(fn () => Verification::factory()->create());

    $this->actingAs(userWithRole(Role::VERIFIER))
        ->get(route('admin.verifications.show', $foreignVerification))
        ->assertNotFound();
});

it('lists only this marketplace\'s companies to its admin', function () {
    $ours = Company::factory()->create();
    onAnotherMarketplace(fn () => Company::factory()->create());

    $this->actingAs(userWithRole(Role::ADMIN))
        ->get(route('admin.companies.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('companies.data', 1)
            ->where('companies.data.0.id', $ours->id));
});

it('keeps roles separate per marketplace', function () {
    $otherMarketplaceAdmin = onAnotherMarketplace(fn () => userWithRole(Role::ADMIN));

    $this->actingAs($otherMarketplaceAdmin)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

it('makes a signed-in user a buyer the first time they use a marketplace', function () {
    $visitor = onAnotherMarketplace(fn () => userWithRole(Role::SUPPLIER));

    $this->actingAs($visitor)->get(route('dashboard'))->assertOk();

    expect($visitor->fresh()->getRoleNames()->all())->toBe([Role::BUYER->value]);
});

/**
 * A marketplace other than the platform's own, reached on its subdomain.
 */
function otherMarketplaceUrl(Tenant $tenant, string $path = '/'): string
{
    return 'http://'.$tenant->slug.'.'.config('tenancy.central_domain').$path;
}

it('closes a marketplace without a current platform plan to everyone but its admins', function () {
    $other = Tenant::factory()->create();

    $this->get(otherMarketplaceUrl($other))->assertStatus(503)
        ->assertInertia(fn (Assert $page) => $page->component('marketplace-unavailable')->where('reason', 'billing'));

    $admin = app(CurrentTenant::class)->runAs($other, fn () => userWithRole(Role::ADMIN));
    $this->actingAs($admin)->get(otherMarketplaceUrl($other, '/admin/billing'))->assertOk();
});

it('closes a suspended marketplace', function () {
    $other = Tenant::factory()->create(['status' => 'suspended']);
    Subscription::factory()->forSubscriber($other, Plan::factory()->create())->create();

    $this->get(otherMarketplaceUrl($other))
        ->assertStatus(503)
        ->assertInertia(fn (Assert $page) => $page->where('reason', 'suspended'));
});

it('keeps the platform\'s own marketplace open without a platform plan', function () {
    $this->tenant->subscriptions()->delete();

    $this->get(route('home'))->assertOk();
    $this->actingAs(userWithRole(Role::BUYER))->get(route('dashboard'))->assertOk();
});

it('shows notifications only on the marketplace they belong to', function () {
    $user = userWithRole(Role::BUYER);
    $user->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'test', 'data' => ['tenant_id' => $this->tenant->id, 'title' => 'Ours']]);
    $user->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'test', 'data' => ['tenant_id' => $this->tenant->id + 999, 'title' => 'Theirs']]);

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('unreadNotificationsCount', 1)
            ->has('notifications.data', 1)
            ->where('notifications.data.0.title', 'Ours'));
});

it('lists only unread notifications when asked, with the type for its icon', function () {
    $user = userWithRole(Role::BUYER);
    $user->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'order.status', 'data' => ['tenant_id' => $this->tenant->id, 'title' => 'New']]);
    $user->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'quote.received', 'data' => ['tenant_id' => $this->tenant->id, 'title' => 'Seen'], 'read_at' => now()]);

    $this->actingAs($user)
        ->get(route('notifications.index', ['filter' => 'unread']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filter', 'unread')
            ->where('counts', ['all' => 2, 'unread' => 1])
            ->has('notifications.data', 1)
            ->where('notifications.data.0.type', 'order.status'));
});

it('limits the platform screens to platform admins', function () {
    $this->actingAs(userWithRole(Role::ADMIN))->get(route('platform.tenants.index'))->assertForbidden();

    $platformAdmin = User::factory()->create();
    $platformAdmin->forceFill(['is_platform_admin' => true])->save();
    onAnotherMarketplace(fn () => null);

    $this->actingAs($platformAdmin)
        ->get(route('platform.tenants.index'))
        ->assertInertia(fn (Assert $page) => $page->has('tenants.data', 2));
});
