<?php

use App\Enums\Role;
use App\Models\User;
use App\Models\UserActivity;
use App\Support\MarketplaceSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('lets users set and remove their own profile photo, cropped to a square', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('profile.photo.update'), ['avatar' => UploadedFile::fake()->image('me.png', 3000, 2000)])->assertSessionHasNoErrors();

    $path = $user->fresh()->avatar;
    expect(array_slice(getimagesizefromstring(Storage::disk('public')->get($path)), 0, 2))->toBe([400, 400]);

    $this->actingAs($user)->delete(route('profile.photo.destroy'));
    expect($user->fresh()->avatar)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('saves a new photo from the admin edit form', function () {
    Storage::fake('public');
    $member = userWithRole(Role::BUYER);
    $platformAdmin = User::factory()->create();
    $platformAdmin->forceFill(['is_platform_admin' => true])->save();

    $this->actingAs($platformAdmin)->post(route('admin.users.update', $member), [
        '_method' => 'put',
        'name' => $member->name,
        'email' => $member->email,
        'avatar' => UploadedFile::fake()->image('member.jpg', 1200, 900),
    ])->assertSessionHasNoErrors();

    expect($member->fresh()->avatar)->not->toBeNull();
});

it('shows the marketplace logo uploaded under settings', function () {
    Storage::fake('public');
    $admin = userWithRole(Role::ADMIN);

    $this->actingAs($admin)->post(route('admin.settings.logo.update'), ['logo' => UploadedFile::fake()->image('logo.png', 400, 120)])->assertSessionHasNoErrors();

    expect(app(MarketplaceSettings::class)->logoUrl())->not->toBeNull();
    $this->get(route('home'))->assertInertia(fn ($page) => $page->where('marketplace.logo_url', app(MarketplaceSettings::class)->logoUrl()));
});

it('deletes activity entries once they are six months old', function () {
    $user = User::factory()->create();
    $old = UserActivity::query()->create(['user_id' => $user->id, 'event' => 'login', 'description' => 'Signed in', 'ip_address' => '1.2.3.4']);
    $old->forceFill(['created_at' => now()->subMonths(6)->subDay()])->save();
    $recent = UserActivity::query()->create(['user_id' => $user->id, 'event' => 'login', 'description' => 'Signed in', 'ip_address' => '1.2.3.4']);
    $recent->forceFill(['created_at' => now()->subMonths(5)])->save();

    $this->artisan('model:prune', ['--model' => [UserActivity::class]])->assertSuccessful();

    expect(UserActivity::query()->pluck('id')->all())->toBe([$recent->id]);
});
