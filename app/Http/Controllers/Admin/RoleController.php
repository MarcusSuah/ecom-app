<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role as RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Role::query()
            ->with('permissions:id,name,module')
            ->withCount('users')   // one query instead of one COUNT per role (F24)
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->string('search')->trim()->toString();
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        $perPage = $request->integer('per_page', 15);
        $perPage = in_array($perPage, [10, 15, 20, 25, 30, 40, 50], true) ? $perPage : 15;

        $roles = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Role $role) => $this->transform($role));

        $permissions = Permission::query()
            ->orderBy('module')
            ->orderBy('name')
            ->get(['id', 'name', 'module', 'description']);

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
            'permissions' => $permissions,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'per_page' => (string) $perPage,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Role::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')],
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $permissionNames = $this->permissionNames($validated['permissions'] ?? []);
        $this->ensureCanGrant($permissionNames);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
        ]);

        $role->syncPermissions($validated['permissions'] ?? []);

        Audit::record('create', 'roles', "Created role {$role->name}", null, ['name' => $role->name, 'permissions' => $permissionNames]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Role {$role->name} created."]);

        return back();
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        // F08: not your own role, and Superadmin/Admin only by the Superadmin.
        Gate::authorize('update', $role);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role->id)],
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        if ($role->isProtected() && $validated['name'] !== $role->name) {
            throw ValidationException::withMessages([
                'name' => 'This role cannot be renamed.',
            ]);
        }

        $oldNames = $role->permissions->pluck('name')->sort()->values()->all();
        $newNames = collect($this->permissionNames($validated['permissions'] ?? []))->sort()->values()->all();
        // Ceiling: every permission being added must be one the editor holds.
        $this->ensureCanGrant(array_values(array_diff($newNames, $oldNames)));

        $oldName = $role->name;
        $role->update(['name' => $validated['name']]);
        $role->syncPermissions($validated['permissions'] ?? []);

        if ($oldName !== $role->name || $oldNames !== $newNames) {
            Audit::record('update', 'roles', "Updated role {$role->name}",
                ['name' => $oldName, 'permissions' => $oldNames],
                ['name' => $role->name, 'permissions' => $newNames]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => "Role {$role->name} saved."]);

        return back();
    }

    public function destroy(Role $role): RedirectResponse
    {
        Gate::authorize('delete', $role);

        if ($role->isProtected()) {
            throw ValidationException::withMessages([
                'role' => 'This role cannot be deleted.',
            ]);
        }

        if (User::role($role->name)->exists()) {
            throw ValidationException::withMessages([
                'role' => 'This role is still assigned to one or more users.',
            ]);
        }

        $role->delete();

        Audit::record('delete', 'roles', "Deleted role {$role->name}", ['name' => $role->name]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Role {$role->name} deleted."]);

        return back();
    }

    /**
     * @param  list<int>  $ids
     * @return list<string>
     */
    private function permissionNames(array $ids): array
    {
        return Permission::query()->whereIn('id', $ids)->pluck('name')->all();
    }

    /** @param  list<string>  $permissionNames */
    private function ensureCanGrant(array $permissionNames): void
    {
        if (! Gate::allows('grant', [Role::class, $permissionNames])) {
            throw ValidationException::withMessages([
                'permissions' => 'You can only give a role permissions that you have yourself.',
            ]);
        }
    }

    private function transform(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'label' => RoleEnum::tryFrom($role->name)?->label() ?? $role->name,
            'is_protected' => $role->isProtected(),
            'users_count' => (int) ($role->users_count ?? User::role($role->name)->count()),
            'permission_ids' => $role->permissions->pluck('id'),
            'permissions_count' => $role->permissions->count(),
            'is_global' => $role->tenant_id === null,
            'can_edit' => Gate::allows('update', $role),
            'can_delete' => Gate::allows('delete', $role) && ! $role->isProtected(),
        ];
    }
}
