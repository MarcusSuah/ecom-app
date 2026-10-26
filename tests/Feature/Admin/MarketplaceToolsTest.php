<?php

use App\Enums\PlanLimit;
use App\Enums\Role;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\BlockedIp;
use App\Models\Category;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserActivity;
use App\Notifications\MarketplaceAnnouncement;
use App\Support\Audit;
use App\Support\CurrentTenant;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function settingsForm(array $overrides = []): array
{
    return [
        'name' => 'Mix Ventures Marketplace',
        'support_email' => 'help@mixventures.test',
        'default_currency' => 'RWF',
        'tagline' => 'Buy in bulk, locally.',
        'support_phone' => '+250 788 000 111',
        'facebook_url' => 'https://facebook.com/mixventures',
        'require_account_approval' => '1',
        'vat_percent' => '18',
        ...$overrides,
    ];
}

it('saves marketplace settings and shows them on the storefront', function () {
    $admin = userWithRole(Role::ADMIN);

    $this->actingAs($admin)->put(route('admin.settings.update'), settingsForm())->assertSessionHasNoErrors();

    expect($this->tenant->fresh()->support_email)->toBe('help@mixventures.test');
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('marketplace.tagline', 'Buy in bulk, locally.')
        ->where('marketplace.support_phone', '+250 788 000 111')
        ->where('marketplace.support_email', 'help@mixventures.test'));
    expect(AuditLog::query()->where('table_name', 'settings')->sole()->new_values)->toHaveKey('tagline', 'Buy in bulk, locally.');
});

it('validates settings and keeps them to admins', function () {
    $this->actingAs(userWithRole(Role::ADMIN))->put(route('admin.settings.update'), settingsForm(['facebook_url' => 'facebook.com/x', 'default_currency' => 'EUR']))
        ->assertSessionHasErrors(['facebook_url', 'default_currency']);

    $this->actingAs(userWithRole(Role::VERIFIER))->get(route('admin.settings.edit'))->assertForbidden();
});

it('sends an announcement to the chosen members of this marketplace only', function () {
    Notification::fake();
    $admin = userWithRole(Role::ADMIN);
    $supplier = userWithRole(Role::SUPPLIER);
    $buyer = userWithRole(Role::BUYER);
    $blocked = userWithRole(Role::SUPPLIER);
    $blocked->forceFill(['blocked_at' => now()])->save();
    $elsewhere = app(CurrentTenant::class)->runAs(Tenant::factory()->create(), fn () => userWithRole(Role::SUPPLIER));

    $this->actingAs($admin)->post(route('admin.notifications.store'), [
        'title' => 'New commission rates', 'body' => 'From next month…', 'audience' => 'suppliers', 'link_url' => '/sell',
    ])->assertSessionHasNoErrors();

    expect(Announcement::sole())->recipients_count->toBe(1);
    Notification::assertSentTo($supplier, MarketplaceAnnouncement::class, fn (MarketplaceAnnouncement $notification) => $notification->toArray($supplier)['tenant_id'] === $this->tenant->id);
    Notification::assertNotSentTo([$buyer, $blocked, $elsewhere], MarketplaceAnnouncement::class);
});

it('records changes people make, for this marketplace\'s audit log', function () {
    $admin = userWithRole(Role::ADMIN);
    $category = Category::factory()->create(['name' => 'Seeds']);

    expect(AuditLog::count())->toBe(0);

    $this->actingAs($admin)->put(route('admin.categories.update', $category), ['name' => 'Seeds and seedlings', 'sort_order' => 3])->assertSessionHasNoErrors();

    $log = AuditLog::sole();
    expect($log)
        ->action->toBe('update')
        ->table_name->toBe('categories')
        ->tenant_id->toBe($this->tenant->id)
        ->performed_by_name->toBe($admin->name)
        ->and($log->old_values['name'])->toBe('Seeds')
        ->and($log->new_values['name'])->toBe('Seeds and seedlings');

    app(CurrentTenant::class)->runAs(Tenant::factory()->create(), fn () => Audit::record('update', 'categories', 'Elsewhere'));

    $this->actingAs($admin)->get(route('admin.audit-logs.index', ['action' => 'update']))->assertInertia(fn (Assert $page) => $page
        ->component('Admin/AuditLogs/Index')
        ->has('logs.data', 1)
        ->where('logs.data.0.description', 'Updated Category Seeds and seedlings'));
});

it('logs sign-ins and failed attempts', function () {
    $member = User::factory()->create(['password' => 'password']);

    $this->post(route('login.store'), ['email' => $member->email, 'password' => 'wrong']);
    $this->post(route('login.store'), ['email' => $member->email, 'password' => 'password']);

    expect(UserActivity::query()->where('event', 'login_failed')->sole()->email)->toBe($member->email)
        ->and(UserActivity::query()->where('event', 'login')->where('user_id', $member->id)->exists())->toBeTrue()
        ->and($member->fresh()->last_login_at)->not->toBeNull();
});

it('lets admins block a member, which signs them out, and block an IP address', function () {
    $admin = userWithRole(Role::ADMIN);
    $member = userWithRole(Role::BUYER);

    $this->actingAs($admin)->post(route('admin.activity.users.block', $member), ['reason' => 'Fraudulent orders'])->assertSessionHasNoErrors();
    expect($member->fresh()->blocked_at)->not->toBeNull();

    $this->actingAs($member->fresh())->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();

    $this->actingAs($admin)->post(route('admin.activity.ips.block'), ['ip_address' => '203.0.113.7'])->assertSessionHasNoErrors();
    expect(BlockedIp::isBlocked('203.0.113.7'))->toBeTrue();
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->get(route('home'))->assertForbidden();
});

it('never lets marketplace admins block staff or people from elsewhere', function () {
    $admin = userWithRole(Role::ADMIN);
    $otherAdmin = userWithRole(Role::ADMIN);
    $outsider = User::factory()->create();

    $this->actingAs($admin)->post(route('admin.activity.users.block', $otherAdmin), ['reason' => 'x'])->assertSessionHasErrors('reason');
    $this->actingAs($admin)->post(route('admin.activity.users.block', $outsider), ['reason' => 'x'])->assertNotFound();
});

it('only lets a company be featured when its plan includes it', function () {
    $admin = userWithRole(Role::ADMIN);
    Plan::factory()->sellerOf($this->tenant)->default()->limit(PlanLimit::FEATURED_LISTING, false)->create();
    $company = Company::factory()->supplier()->create();

    $this->actingAs($admin)->get(route('admin.companies.index'))->assertInertia(fn (Assert $page) => $page->where('companies.data.0.can_be_featured', false));
    $this->actingAs($admin)->put(route('admin.companies.feature', $company), ['is_featured' => true])->assertSessionHasErrors('is_featured');
});

it('lets admins add a parent category that is not listed yet', function () {
    $this->actingAs(userWithRole(Role::ADMIN))
        ->post(route('admin.categories.store'), ['name' => 'Solar panels', 'new_parent_name' => 'Energy', 'sort_order' => 0])
        ->assertSessionHasNoErrors();

    $child = Category::query()->where('name', 'Solar panels')->sole();
    expect($child->parent->name)->toBe('Energy')->and($child->parent->parent_id)->toBeNull();

    $this->actingAs(userWithRole(Role::ADMIN))
        ->post(route('admin.categories.store'), ['name' => 'Inverters', 'new_parent_name' => 'Energy', 'sort_order' => 0])
        ->assertSessionHasErrors('new_parent_name');
});

it('loads the latest notifications for the bell and marks one read when opened', function () {
    $member = userWithRole(Role::SUPPLIER);
    $announcement = new Announcement(['title' => 'Hello', 'body' => 'Welcome', 'audience' => 'everyone', 'link_url' => '/sell']);
    $announcement->forceFill(['recipients_count' => 1])->save();
    $member->notify(new MarketplaceAnnouncement($announcement));

    $this->actingAs($member)->getJson(route('notifications.recent'))
        ->assertOk()
        ->assertJsonPath('unread', 1)
        ->assertJsonPath('notifications.0.title', 'Hello');

    $id = $member->notifications()->sole()->id;
    $this->actingAs($member)->get(route('notifications.open', $id))->assertRedirect('/sell');
    expect($member->unreadNotifications()->count())->toBe(0);
});
