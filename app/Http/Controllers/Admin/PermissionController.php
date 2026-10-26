<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role as RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PermissionController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Permission::query()
            ->withCount('roles')
            ->orderBy('module')
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->string('search')->trim()->toString();
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('module', 'like', "%{$search}%");
            });
        }

        if ($request->filled('module')) {
            $query->where('module', $request->string('module')->toString());
        }

        $perPage = $request->integer('per_page', 15);
        $perPage = in_array($perPage, [10, 15, 20, 25, 30, 40, 50], true) ? $perPage : 15;

        $permissions = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Permission $permission) => $this->transform($permission));

        return Inertia::render('Admin/Permissions/Index', [
            'permissions' => $permissions,
            'modules' => Permission::query()->distinct()->orderBy('module')->pluck('module')->filter()->values(),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'module' => $request->string('module')->toString(),
                'per_page' => (string) $perPage,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('permissions', 'name')],
            'module' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $permission = Permission::create([
            ...$validated,
            'guard_name' => 'web',
        ]);

        // The super admin holds every permission, including new ones.
        Role::query()->withoutGlobalScopes()->where('name', RoleEnum::SUPER_ADMIN->value)->whereNull('tenant_id')->first()?->givePermissionTo($permission);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Permission {$permission->name} created."]);

        return back();
    }

    public function update(Request $request, Permission $permission): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('permissions', 'name')->ignore($permission->id)],
            'module' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        // F08: the application checks these names in code; renaming one breaks access.
        if ($validated['name'] !== $permission->name && $this->isSystemPermission($permission)) {
            throw ValidationException::withMessages([
                'name' => 'This permission is used by the application and cannot be renamed.',
            ]);
        }

        $permission->update($validated);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Permission {$permission->name} saved."]);

        return back();
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        if ($this->isSystemPermission($permission)) {
            throw ValidationException::withMessages([
                'permission' => 'This permission is used by the application and cannot be deleted.',
            ]);
        }

        if ($permission->roles()->exists()) {
            throw ValidationException::withMessages([
                'permission' => 'This permission is still assigned to one or more roles.',
            ]);
        }

        $permission->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => "Permission {$permission->name} deleted."]);

        return back();
    }

    private function isSystemPermission(Permission $permission): bool
    {
        return in_array($permission->name, RolePermissionSeeder::systemPermissions(), true);
    }

    private function transform(Permission $permission): array
    {
        return [
            'id' => $permission->id,
            'name' => $permission->name,
            'module' => $permission->module,
            'description' => $permission->description,
            'roles_count' => $permission->roles_count,
            // Checked in code by name, so it cannot be renamed or deleted.
            'is_system' => $this->isSystemPermission($permission),
        ];
    }
}
