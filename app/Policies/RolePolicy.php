<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

/**
 * Roles (Phase 4, F08). Permission alone is not enough:
 *
 *  - Ceiling: you can only put permissions into a role that you hold
 *    yourself, so roles.update can never be used to gain more access.
 *  - You cannot edit or delete a role you hold (no self-promotion).
 *  - The protected roles (Superadmin, Admin) can only be changed by the
 *    Superadmin.
 *
 * The Superadmin passes every check through App\Support\Authorization.
 * Before Phase 4 this policy existed but every method returned false and
 * nothing called it.
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can('roles.view');
    }

    public function create(User $user): bool
    {
        return $user->can('roles.create');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can('roles.update') && $this->mayChange($user, $role);
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can('roles.delete') && $this->mayChange($user, $role);
    }

    /**
     * May $user put these permissions into a role?
     *
     * @param  list<string>  $permissionNames
     */
    public function grant(User $user, array $permissionNames): bool
    {
        return self::holdsAll($user, $permissionNames);
    }

    public function restore(User $user, Role $role): bool
    {
        return false;
    }

    public function forceDelete(User $user, Role $role): bool
    {
        return false;
    }

    /** @param  list<string>  $permissionNames */
    public static function holdsAll(User $user, array $permissionNames): bool
    {
        // Each name goes through the Gate, so an API token's abilities limit
        // this too (App\Support\Authorization), not only the user's roles.
        foreach (array_unique($permissionNames) as $name) {
            if (! $user->can($name)) {
                return false;
            }
        }

        return true;
    }

    private function mayChange(User $user, Role $role): bool
    {
        return ! $role->isProtected() && ! $user->hasRole($role->name);
    }
}
