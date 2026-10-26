import { Head, router, useForm } from '@inertiajs/react';
import { Lock, Pencil, Plus, Search, ShieldCheck, Trash2 } from 'lucide-react';
import { type FormEvent, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTableFooter } from '@/components/data-table-footer';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { usePermissions } from '@/hooks/use-permissions';
import roleRoutes from '@/routes/admin/roles';
import type { Paginated } from '@/types';

type Role = {
    id: number;
    name: string;
    label: string;
    is_protected: boolean;
    is_global: boolean;
    users_count: number;
    permissions_count: number;
    permission_ids: number[];
    can_edit: boolean;
    can_delete: boolean;
};

type Permission = {
    id: number;
    name: string;
    module: string | null;
    description: string | null;
};

type Props = {
    roles: Paginated<Role>;
    permissions: Permission[];
    filters: { search: string; per_page: string };
};

export default function RolesIndex({ roles, permissions, filters }: Props) {
    const { can } = usePermissions();
    const [search, setSearch] = useState(filters.search ?? '');
    const [editing, setEditing] = useState<Role | 'new' | null>(null);
    const [deleting, setDeleting] = useState<Role | null>(null);
    const form = useForm<{ name: string; permissions: number[] }>({
        name: '',
        permissions: [],
    });

    const modules = useMemo(() => {
        const grouped = new Map<string, Permission[]>();
        permissions.forEach((permission) => {
            const module = permission.module ?? 'Other';
            grouped.set(module, [...(grouped.get(module) ?? []), permission]);
        });

        return [...grouped.entries()];
    }, [permissions]);

    const updateFilters = (next: Partial<Props['filters']>) =>
        router.get(
            roleRoutes.index.url(),
            {
                search: (next.search ?? filters.search) || undefined,
                per_page: next.per_page ?? filters.per_page,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    useEffect(() => {
        const timeout = setTimeout(
            () =>
                search !== (filters.search ?? '') && updateFilters({ search }),
            350,
        );

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const open = (role: Role | 'new') => {
        form.clearErrors();
        form.setData(
            role === 'new'
                ? { name: '', permissions: [] }
                : { name: role.name, permissions: role.permission_ids },
        );
        setEditing(role);
    };

    const togglePermission = (id: number, on: boolean) =>
        form.setData(
            'permissions',
            on
                ? [...new Set([...form.data.permissions, id])]
                : form.data.permissions.filter((value) => value !== id),
        );

    const toggleModule = (items: Permission[], on: boolean) => {
        const ids = items.map((item) => item.id);
        form.setData(
            'permissions',
            on
                ? [...new Set([...form.data.permissions, ...ids])]
                : form.data.permissions.filter((value) => !ids.includes(value)),
        );
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        };

        if (editing === 'new') {
            form.post(roleRoutes.store.url(), options);
        } else if (editing) {
            form.put(roleRoutes.update.url(editing.id), options);
        }
    };

    const isSystem =
        editing !== null && editing !== 'new' && editing.is_protected;

    return (
        <>
            <Head title="Roles" />
            <div className="flex flex-1 flex-col gap-6 p-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Roles
                        </h1>
                        <p className="text-muted-foreground">
                            Group permissions into roles, then give roles to
                            members.
                        </p>
                    </div>
                    {can('roles.create') && (
                        <Button onClick={() => open('new')}>
                            <Plus className="mr-2 h-4 w-4" />
                            New role
                        </Button>
                    )}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>All roles</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="mb-6 flex flex-col gap-3 md:flex-row">
                            <div className="relative flex-1">
                                <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    placeholder="Search roles..."
                                    value={search}
                                    className="w-full max-w-sm pl-9"
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    aria-label="Search roles"
                                />
                            </div>
                            <Select
                                value={filters.per_page}
                                onValueChange={(value) =>
                                    updateFilters({ per_page: value })
                                }
                            >
                                <SelectTrigger
                                    className="w-full md:w-[140px]"
                                    aria-label="Rows per page"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {['10', '15', '25', '50'].map((size) => (
                                        <SelectItem key={size} value={size}>
                                            {size} per page
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Role</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead className="text-right">
                                            Permissions
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Members
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {roles.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={5}
                                                className="h-32 text-center"
                                            >
                                                No roles found.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        roles.data.map((role) => (
                                            <TableRow key={role.id}>
                                                <TableCell>
                                                    <div className="flex items-center gap-3">
                                                        <span className="flex size-9 items-center justify-center rounded-full bg-sky-50 text-sky-700 dark:bg-sky-950 dark:text-sky-300">
                                                            <ShieldCheck
                                                                className="h-4 w-4"
                                                                aria-hidden="true"
                                                            />
                                                        </span>
                                                        <div>
                                                            <div className="font-medium">
                                                                {role.label}
                                                            </div>
                                                            <div className="font-mono text-xs text-muted-foreground">
                                                                {role.name}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    {role.is_protected ? (
                                                        <Badge
                                                            variant="outline"
                                                            className="gap-1"
                                                        >
                                                            <Lock
                                                                className="h-3 w-3"
                                                                aria-hidden="true"
                                                            />
                                                            System
                                                        </Badge>
                                                    ) : (
                                                        <Badge variant="secondary">
                                                            {role.is_global
                                                                ? 'Shared'
                                                                : 'This marketplace'}
                                                        </Badge>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {role.permissions_count}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {role.users_count}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex justify-end gap-1">
                                                        <Button
                                                            size="icon"
                                                            variant="outline"
                                                            title={
                                                                role.can_edit
                                                                    ? 'Edit'
                                                                    : 'View'
                                                            }
                                                            onClick={() =>
                                                                open(role)
                                                            }
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                            <span className="sr-only">
                                                                {role.can_edit
                                                                    ? 'Edit'
                                                                    : 'View'}{' '}
                                                                {role.label}
                                                            </span>
                                                        </Button>
                                                        {role.can_delete && (
                                                            <Button
                                                                size="icon"
                                                                variant="destructive"
                                                                title="Delete"
                                                                onClick={() =>
                                                                    setDeleting(
                                                                        role,
                                                                    )
                                                                }
                                                            >
                                                                <Trash2 className="h-4 w-4" />
                                                                <span className="sr-only">
                                                                    Delete{' '}
                                                                    {role.label}
                                                                </span>
                                                            </Button>
                                                        )}
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </div>

                        <DataTableFooter page={roles} noun="role(s)" />
                    </CardContent>
                </Card>
            </div>

            <Dialog
                open={editing !== null}
                onOpenChange={(value) => !value && setEditing(null)}
            >
                <DialogContent className="sm:max-w-[640px]">
                    <form onSubmit={submit}>
                        <DialogHeader>
                            <DialogTitle>
                                {editing === 'new'
                                    ? 'New role'
                                    : editing?.can_edit
                                      ? `Edit ${editing.label}`
                                      : editing?.label}
                            </DialogTitle>
                            <DialogDescription>
                                {editing !== 'new' &&
                                editing &&
                                !editing.can_edit
                                    ? 'You can see this role but not change it: it is a system role, or one you hold yourself.'
                                    : 'Choose the permissions this role gives. You can only give permissions you have.'}
                            </DialogDescription>
                        </DialogHeader>

                        <div className="grid max-h-[60vh] gap-5 overflow-y-auto py-4 pr-1">
                            <div className="grid gap-2">
                                <Label htmlFor="role-name">Name</Label>
                                <Input
                                    id="role-name"
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                    disabled={
                                        isSystem ||
                                        (editing !== 'new' &&
                                            editing !== null &&
                                            !editing.can_edit)
                                    }
                                    placeholder="e.g. support_agent"
                                />
                                <InputError message={form.errors.name} />
                            </div>

                            <div className="grid gap-4">
                                {modules.map(([module, items]) => {
                                    const checkedCount = items.filter((item) =>
                                        form.data.permissions.includes(item.id),
                                    ).length;
                                    const locked =
                                        editing !== 'new' &&
                                        editing !== null &&
                                        !editing.can_edit;

                                    return (
                                        <fieldset
                                            key={module}
                                            className="rounded-md border p-3"
                                        >
                                            <legend className="px-1 text-sm font-medium">
                                                <label className="flex items-center gap-2">
                                                    <Checkbox
                                                        checked={
                                                            checkedCount ===
                                                            items.length
                                                                ? true
                                                                : checkedCount >
                                                                    0
                                                                  ? 'indeterminate'
                                                                  : false
                                                        }
                                                        disabled={locked}
                                                        onCheckedChange={(
                                                            value,
                                                        ) =>
                                                            toggleModule(
                                                                items,
                                                                value === true,
                                                            )
                                                        }
                                                    />
                                                    {module}
                                                    <span className="text-xs font-normal text-muted-foreground">
                                                        {checkedCount}/
                                                        {items.length}
                                                    </span>
                                                </label>
                                            </legend>
                                            <div className="grid gap-2 sm:grid-cols-2">
                                                {items.map((permission) => (
                                                    <label
                                                        key={permission.id}
                                                        className="flex items-start gap-2 text-sm"
                                                    >
                                                        <Checkbox
                                                            className="mt-0.5"
                                                            checked={form.data.permissions.includes(
                                                                permission.id,
                                                            )}
                                                            disabled={locked}
                                                            onCheckedChange={(
                                                                value,
                                                            ) =>
                                                                togglePermission(
                                                                    permission.id,
                                                                    value ===
                                                                        true,
                                                                )
                                                            }
                                                        />
                                                        <span>
                                                            <span className="block font-mono text-xs">
                                                                {
                                                                    permission.name
                                                                }
                                                            </span>
                                                            {permission.description && (
                                                                <span className="text-xs text-muted-foreground">
                                                                    {
                                                                        permission.description
                                                                    }
                                                                </span>
                                                            )}
                                                        </span>
                                                    </label>
                                                ))}
                                            </div>
                                        </fieldset>
                                    );
                                })}
                            </div>
                            <InputError message={form.errors.permissions} />
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setEditing(null)}
                            >
                                {editing !== 'new' &&
                                editing &&
                                !editing.can_edit
                                    ? 'Close'
                                    : 'Cancel'}
                            </Button>
                            {(editing === 'new' ||
                                (editing && editing.can_edit)) && (
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    {form.processing
                                        ? 'Saving...'
                                        : editing === 'new'
                                          ? 'Create role'
                                          : 'Save changes'}
                                </Button>
                            )}
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(value) => !value && setDeleting(null)}
                title="Delete role"
                description={
                    <>
                        Delete <strong>{deleting?.label}</strong>? Roles still
                        given to members cannot be deleted.
                    </>
                }
                confirmLabel="Delete role"
                variant="destructive"
                onConfirm={() => {
                    if (deleting) {
                        router.delete(roleRoutes.destroy.url(deleting.id), {
                            preserveScroll: true,
                            onError: (errors) =>
                                toast.error(
                                    Object.values(errors)[0] ??
                                        'Could not delete.',
                                ),
                        });
                    }

                    setDeleting(null);
                }}
            />
        </>
    );
}

RolesIndex.layout = {
    breadcrumbs: [{ title: 'Roles', href: roleRoutes.index() }],
};
