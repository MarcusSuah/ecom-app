<?php

use App\Enums\Role;
use App\Models\Category;
use App\Models\Tenant;
use App\Support\CurrentTenant;

it('lets admins add a category to their marketplace', function () {
    $parent = Category::factory()->create(['name' => 'Agriculture']);

    $this->actingAs(userWithRole(Role::ADMIN))
        ->post(route('admin.categories.store'), ['name' => 'Seeds & Fertiliser', 'parent_id' => $parent->id, 'sort_order' => 5])
        ->assertSessionHasNoErrors();

    $category = Category::query()->where('name', 'Seeds & Fertiliser')->sole();
    expect($category->parent_id)->toBe($parent->id)
        ->and($category->slug)->toBe('seeds-fertiliser')
        ->and($category->tenant_id)->toBe($this->tenant->id);
});

it('refuses category changes from non-admins', function () {
    $this->actingAs(userWithRole(Role::VERIFIER))
        ->post(route('admin.categories.store'), ['name' => 'Toys', 'sort_order' => 0])
        ->assertForbidden();

    expect(Category::count())->toBe(0);
});

it('only accepts a top-level category of this marketplace as the parent', function (Category $parent) {
    $this->actingAs(userWithRole(Role::ADMIN))
        ->post(route('admin.categories.store'), ['name' => 'Child', 'parent_id' => $parent->id, 'sort_order' => 0])
        ->assertSessionHasErrors(['parent_id' => 'Choose a top-level category as the parent.']);
})->with([
    'a sub-category' => [fn () => Category::factory()->for(Category::factory(), 'parent')->create()],
    'another marketplace\'s category' => [fn () => app(CurrentTenant::class)->runAs(Tenant::factory()->create(), fn () => Category::factory()->create())],
]);

it('lets two marketplaces use the same category name and slug', function () {
    $other = Tenant::factory()->create();
    app(CurrentTenant::class)->runAs($other, fn () => Category::factory()->create(['name' => 'Electronics']));

    $this->actingAs(userWithRole(Role::ADMIN))
        ->post(route('admin.categories.store'), ['name' => 'Electronics', 'sort_order' => 0])
        ->assertSessionHasNoErrors();

    expect(Category::query()->where('name', 'Electronics')->sole()->slug)->toBe('electronics');
});

it('does not delete a category that still has sub-categories', function () {
    $parent = Category::factory()->create();
    Category::factory()->for($parent, 'parent')->create();

    $this->actingAs(userWithRole(Role::ADMIN))
        ->delete(route('admin.categories.destroy', $parent))
        ->assertSessionHasErrors(['category' => 'Move or delete its sub-categories first.']);

    expect($parent->fresh())->not->toBeNull();
});
