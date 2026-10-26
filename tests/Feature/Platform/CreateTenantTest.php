<?php

use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Models\Category;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

function platformAdmin(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['is_platform_admin' => true])->save();

    return $admin;
}

it('opens a marketplace with its defaults, first admin and platform plan', function () {
    Notification::fake();
    $plan = Plan::factory()->paid(4900)->withTrial(30)->create(['currency' => 'USD']);

    $this->actingAs(platformAdmin())
        ->post(route('platform.tenants.store'), [
            'name' => 'Huye Market',
            'slug' => 'Huye',
            'default_currency' => 'RWF',
            'admin_name' => 'Jean Admin',
            'admin_email' => 'jean@huye.example',
            'plan_id' => $plan->id,
        ])
        ->assertSessionHasNoErrors();

    $tenant = Tenant::query()->where('slug', 'huye')->sole();
    $admin = User::query()->where('email', 'jean@huye.example')->sole();

    app(CurrentTenant::class)->runAs($tenant, function () use ($tenant, $admin) {
        expect(Category::count())->toBe(10)
            ->and(Plan::query()->forSellersOf($tenant)->where('is_default', true)->count())->toBe(1)
            ->and($admin->hasRole(Role::ADMIN->value))->toBeTrue()
            ->and($tenant->currentSubscription()->status)->toBe(SubscriptionStatus::TRIALING);
    });

    Notification::assertSentTo($admin, ResetPassword::class);
});

it('does not reuse a subdomain or allow reserved ones', function (string $slug) {
    Tenant::factory()->create(['slug' => 'taken']);

    $this->actingAs(platformAdmin())
        ->post(route('platform.tenants.store'), [
            'name' => 'Clash', 'slug' => $slug, 'default_currency' => 'RWF',
            'admin_name' => 'A', 'admin_email' => 'a@example.com', 'plan_id' => Plan::factory()->create()->id,
        ])
        ->assertSessionHasErrors('slug');
})->with(['taken', 'admin', 'not a slug']);
