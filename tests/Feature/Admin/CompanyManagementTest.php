<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

it('suspends a business with a reason and reinstates it', function () {
    $company = Company::factory()->supplier()->create();
    $admin = userWithRole(Role::ADMIN);

    $this->actingAs($admin)->post(route('admin.companies.suspend', $company), ['reason' => ''])->assertSessionHasErrors('reason');
    $this->actingAs($admin)->post(route('admin.companies.suspend', $company), ['reason' => 'Unpaid fees'])->assertRedirect();
    expect($company->fresh())->suspended_reason->toBe('Unpaid fees')->and($company->fresh()->isListable())->toBeFalse();

    $this->actingAs($admin)->delete(route('admin.companies.reinstate', $company))->assertRedirect();
    expect($company->fresh()->isListable())->toBeTrue();
});

it('keeps suspension to staff with companies.suspend', function () {
    $company = Company::factory()->supplier()->create();

    $this->actingAs(userWithRole(Role::SUPPLIER))->post(route('admin.companies.suspend', $company), ['reason' => 'x'])->assertForbidden();
});

it('lets the super admin add products and photos for a business without signing in as its owner', function () {
    Storage::fake('public');
    $company = Company::factory()->supplier()->create();
    $superAdmin = userWithRole(Role::SUPER_ADMIN);

    $this->actingAs($superAdmin)->post(route('admin.companies.manage', $company))->assertRedirect(route('supplier.products.index'));
    $this->actingAs($superAdmin)->get(route('supplier.products.index'))->assertInertia(fn (Assert $page) => $page
        ->where('auth.company.slug', $company->slug)
        ->where('auth.actingCompany.name', $company->name));

    $this->actingAs($superAdmin)->post(route('supplier.products.store'), productPayload([
        'name' => 'Added by the super admin',
        'images' => [UploadedFile::fake()->image('a.jpg', 800, 600)],
    ]))->assertSessionHasNoErrors();

    $product = Product::query()->where('name', 'Added by the super admin')->sole();
    expect($product->company_id)->toBe($company->id)->and($product->images()->count())->toBe(1);

    $this->actingAs($superAdmin)->delete(route('acting-company.destroy'))->assertRedirect(route('admin.companies.index'));
    $this->actingAs($superAdmin)->get(route('admin.companies.index'))->assertInertia(fn (Assert $page) => $page->where('auth.actingCompany', null));
});

it('only lets the super admin manage other businesses', function () {
    $company = Company::factory()->supplier()->create();

    $this->actingAs(userWithRole(Role::ADMIN))->post(route('admin.companies.manage', $company))->assertForbidden();
});

it('never lets a super admin be deactivated', function () {
    $superAdmin = userWithRole(Role::SUPER_ADMIN);
    $platformAdmin = userWithRole(Role::ADMIN);
    $platformAdmin->forceFill(['is_platform_admin' => true])->save();

    $this->actingAs($platformAdmin)->post(route('admin.users.deactivate', $superAdmin))->assertSessionHasErrors('user');

    expect($superAdmin->fresh()->is_active)->toBeTrue();
});
