<?php

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\AccountAwaitingApproval;
use App\Notifications\AccountReviewed;
use App\Support\MarketplaceSettings;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

function registerAccount(): User
{
    test()->post(route('register.store'), [
        'name' => 'Aline Uwase',
        'email' => 'aline@example.rw',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    return User::query()->where('email', 'aline@example.rw')->sole();
}

function pendingMember(): User
{
    $user = userWithRole(Role::BUYER);
    $user->forceFill(['status' => UserStatus::PENDING])->save();

    return $user;
}

it('makes new sign-ups wait for approval and tells the marketplace admins', function () {
    $this->skipUnlessFortifyHas(Features::registration());
    Notification::fake();
    $admin = userWithRole(Role::ADMIN);

    $user = registerAccount();

    expect($user->status)->toBe(UserStatus::PENDING);
    Notification::assertSentTo($admin, AccountAwaitingApproval::class);
});

it('lets sign-ups in straight away when the marketplace does not require approval', function () {
    $this->skipUnlessFortifyHas(Features::registration());
    app(MarketplaceSettings::class)->save(['require_account_approval' => '0']);

    expect(registerAccount()->status)->toBe(UserStatus::APPROVED);
});

it('sends accounts waiting for approval to the waiting page from every page', function (string $page) {
    $user = pendingMember();

    $this->actingAs($user)->get(route($page))->assertRedirect(route('account.pending'));
    $this->actingAs($user)->get(route('account.pending'))->assertInertia(fn (Assert $view) => $view
        ->component('auth/pending-approval')
        ->where('status', 'pending'));
})->with(['dashboard', 'home', 'orders.index', 'cart.show']);

it('keeps the storefront open to guests', function () {
    $this->get(route('home'))->assertOk();
});

it('lets marketplace admins approve an account, which then gets in', function () {
    Notification::fake();
    $user = pendingMember();
    $admin = userWithRole(Role::ADMIN);

    $this->actingAs($admin)->post(route('admin.users.approve', $user))->assertRedirect();

    expect($user->fresh())->status->toBe(UserStatus::APPROVED)->approved_by->toBe($admin->id);
    Notification::assertSentTo($user, AccountReviewed::class, fn (AccountReviewed $notification) => $notification->approved);
    $this->actingAs($user->fresh())->get(route('dashboard'))->assertOk();
});

it('turns an account down with a reason the user sees', function () {
    Notification::fake();
    $user = pendingMember();
    $admin = userWithRole(Role::ADMIN);

    $this->actingAs($admin)->post(route('admin.users.reject', $user), ['reason' => ''])->assertSessionHasErrors('reason');
    $this->actingAs($admin)->post(route('admin.users.reject', $user), ['reason' => 'We could not confirm your business.'])->assertRedirect();

    Notification::assertSentTo($user, AccountReviewed::class, fn (AccountReviewed $notification) => ! $notification->approved);
    $this->actingAs($user->fresh())->get(route('account.pending'))->assertInertia(fn (Assert $view) => $view
        ->where('status', 'rejected')
        ->where('reason', 'We could not confirm your business.'));
});

it('only lets user managers approve accounts', function () {
    $user = pendingMember();

    $this->actingAs(userWithRole(Role::SUPPLIER))->post(route('admin.users.approve', $user))->assertForbidden();

    expect($user->fresh()->status)->toBe(UserStatus::PENDING);
});
