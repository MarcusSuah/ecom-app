import { usePage } from '@inertiajs/react';
import type { SharedData } from '@/types';

/**
 * Mirrors the server's checks for showing/hiding UI. The server shares the
 * full permission list for platform admins, so `can()` needs no special case.
 * This only hides UI — the routes still enforce permissions.
 */
export function usePermissions() {
    const { auth } = usePage<SharedData>().props;
    const permissions = auth.user?.permissions ?? [];
    const roles = auth.user?.roles ?? [];

    const can = (permission?: string) =>
        !permission || permissions.includes(permission);
    const hasRole = (role: string) => roles.includes(role);

    return {
        can,
        hasRole,
        permissions,
        roles,
        isPlatformAdmin: Boolean(auth.user?.is_platform_admin),
    };
}
