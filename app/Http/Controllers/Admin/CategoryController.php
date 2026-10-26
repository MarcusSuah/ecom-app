<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Category::class);

        $categories = Category::query()
            ->with('parent:id,name')
            ->withCount('children')
            ->ordered()
            ->paginate(50)
            ->through(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'sort_order' => $category->sort_order,
                'parent_id' => $category->parent_id,
                'parent_name' => $category->parent?->name,
                'children_count' => $category->children_count,
            ]);

        return Inertia::render('Admin/Categories/Index', [
            'categories' => $categories,
            'parentOptions' => Category::query()->whereNull('parent_id')->ordered()->get(['id', 'name']),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        DB::transaction(fn () => Category::create($this->attributes($request)));

        Inertia::flash('toast', ['type' => 'success', 'message' => $request->filled('new_parent_name') ? 'Category and its new parent added.' : 'Category added.']);

        return back();
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        if (($request->filled('parent_id') || $request->filled('new_parent_name')) && $category->children()->exists()) {
            throw ValidationException::withMessages(['parent_id' => 'A category with sub-categories must stay at the top level.']);
        }

        DB::transaction(fn () => $category->update($this->attributes($request)));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Category saved.']);

        return back();
    }

    /**
     * The validated fields, creating the typed-in parent (top level, last in
     * order) when one was given instead of an existing parent.
     *
     * @return array<string, mixed>
     */
    private function attributes(CategoryRequest $request): array
    {
        $attributes = Arr::except($request->validated(), ['new_parent_name']);

        if ($request->filled('new_parent_name')) {
            $attributes['parent_id'] = Category::query()->create([
                'name' => $request->string('new_parent_name')->trim()->toString(),
                'parent_id' => null,
                'sort_order' => (int) Category::query()->whereNull('parent_id')->max('sort_order') + 10,
            ])->id;
        }

        return $attributes;
    }

    public function destroy(Category $category): RedirectResponse
    {
        Gate::authorize('delete', $category);

        if ($category->children()->exists()) {
            throw ValidationException::withMessages(['category' => 'Move or delete its sub-categories first.']);
        }

        if (Product::query()->where('category_id', $category->id)->exists()) {
            throw ValidationException::withMessages(['category' => 'Products are listed in this category. Move them to another category first.']);
        }

        $category->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Category deleted.']);

        return back();
    }
}
