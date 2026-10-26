<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

/**
 * Accounts are shared by every marketplace. A marketplace admin manages
 * only the people who have a role on their marketplace, never a platform
 * admin, and never deletes an account (that would remove it from every
 * marketplace) — only platform admins can, via Gate::before.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function view(User $user, User $target): bool
    {
        return $user->can('users.view') && $this->isManageableMember($target);
    }

    /**
     * Name, email and avatar belong to the shared account. Letting one
     * marketplace's admin change another person's email would let them
     * take over that account everywhere, so only platform admins can.
     */
    public function update(User $user, User $target): bool
    {
        return false;
    }

    /**
     * Blocking or unblocking an account affects every marketplace, so it is
     * left to platform admins.
     */
    public function deactivate(User $user, User $target): bool
    {
        return false;
    }

    public function delete(User $user, User $target): bool
    {
        return false;
    }

    public function approve(User $user, User $target): bool
    {
        return $user->can('users.update') && $this->isManageableMember($target);
    }

    /**
     * Change a member's roles on this marketplace. Every role added or
     * removed must be one the editor could have built themselves.
     *
     * @param  list<string>  $changedRoles
     */
    public function assignRoles(User $user, User $target, array $changedRoles = []): bool
    {
        if (! $user->can('users.update') || ! $this->isManageableMember($target)) {
            return false;
        }

        foreach ($changedRoles as $roleName) {
            if (! self::canGiveRole($user, $roleName)) {
                return false;
            }
        }

        return true;
    }

    /**
     * A role can be given by someone who holds every permission in it.
     */
    public static function canGiveRole(User $user, string $roleName): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();

        return $role !== null && RolePolicy::holdsAll($user, array_values($role->getPermissionNames()->all()));
    }

    private function isManageableMember(User $target): bool
    {
        return ! $target->isPlatformAdmin() && $target->roles()->exists();
    }
}
