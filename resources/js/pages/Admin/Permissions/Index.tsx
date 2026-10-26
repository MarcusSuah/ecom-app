import { Head, router, useForm } from '@inertiajs/react';
import { KeyRound, Lock, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { type FormEvent, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTableFooter } from '@/components/data-table-footer';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { Textarea } from '@/components/ui/textarea';
import { usePermissions } from '@/hooks/use-permissions';
import permissionRoutes from '@/routes/admin/permissions';
import type { Paginated } from '@/types';

type Permission = {
    id: number;
    name: string;
    module: string | null;
    description: string | null;
    roles_count: number;
    is_system: boolean;
};

type Props = {
    permissions: Paginated<Permission>;
    modules: string[];
    filters: { search: string; module: string; per_page: string };
};

export default function PermissionsIndex({
    permissions,
    modules,
    filters,
}: Props) {
    const { can } = usePermissions();
    const [search, setSearch] = useState(filters.search ?? '');
    const [editing, setEditing] = useState<Permission | 'new' | null>(null);
    const [deleting, setDeleting] = useState<Permission | null>(null);
    const form = useForm({ name: '', module: '', description: '' });

    const updateFilters = (next: Partial<Props['filters']>) =>
        router.get(
            permissionRoutes.index.url(),
            {
                search: (next.search ?? filters.search) || undefined,
                module: (next.module ?? filters.module) || undefined,
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

    const open = (permission: Permission | 'new') => {
        form.clearErrors();
        form.setData(
            permission === 'new'
                ? { name: '', module: filters.module || '', description: '' }
                : {
                      name: permission.name,
                      module: permission.module ?? '',
                      description: permission.description ?? '',
                  },
        );
        setEditing(permission);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        };

        if (editing === 'new') {
            form.post(permissionRoutes.store.url(), options);
        } else if (editing) {
            form.put(permissionRoutes.update.url(editing.id), options);
        }
    };

    const canEdit =
        editing === 'new'
            ? can('permissions.create')
            : can('permissions.update');

    return (
        <>
            <Head title="Permissions" />
            <div className="flex flex-1 flex-col gap-6 p-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Permissions
                        </h1>
                        <p className="text-muted-foreground">
                            What each permission allows. Put them into roles to
                            give access.
                        </p>
                    </div>
                    {can('permissions.create') && (
                        <Button onClick={() => open('new')}>
                            <Plus className="mr-2 h-4 w-4" />
                            New permission
                        </Button>
                    )}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>All permissions</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="mb-6 flex flex-col gap-3 md:flex-row">
                            <div className="relative flex-1">
                                <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    placeholder="Search by name, module or description..."
                                    value={search}
                                    className="w-full max-w-sm pl-9"
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    aria-label="Search permissions"
                                />
                            </div>
                            <Select
                                value={filters.module || 'all'}
                                onValueChange={(value) =>
                                    updateFilters({
                                        module: value === 'all' ? '' : value,
                                    })
                                }
                            >
                                <SelectTrigger
                                    className="w-full md:w-[200px]"
                                    aria-label="Module"
                                >
                                    <SelectValue placeholder="Module" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All modules
                                    </SelectItem>
                                    {modules.map((module) => (
                                        <SelectItem key={module} value={module}>
                                            {module}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
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
                                        <TableHead>Permission</TableHead>
                                        <TableHead>Module</TableHead>
                                        <TableHead className="text-right">
                                            Roles
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {permissions.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={4}
                                                className="h-32 text-center"
                                            >
                                                No permissions found.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        permissions.data.map((permission) => (
                                            <TableRow key={permission.id}>
                                                <TableCell>
                                                    <div className="flex items-center gap-3">
                                                        <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                                                            <KeyRound
                                                                className="h-4 w-4"
                                                                aria-hidden="true"
                                                            />
                                                        </span>
                                                        <div>
                                                            <div className="flex items-center gap-2 font-mono text-sm font-medium">
                                                                {
                                                                    permission.name
                                                                }
                                                                {permission.is_system && (
                                                                    <Badge
                                                                        variant="outline"
                                                                        className="gap-1 font-sans"
                                                                        title="Used by the application; cannot be renamed or deleted"
                                                                    >
                                                                        <Lock
                                                                            className="h-3 w-3"
                                                                            aria-hidden="true"
                                                                        />
                                                                        System
                                                                    </Badge>
                                                                )}
                                                            </div>
                                                            <div className="text-sm text-muted-foreground">
                                                                {permission.description ??
                                                                    '—'}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    {permission.module ? (
                                                        <Badge variant="secondary">
                                                            {permission.module}
                                                        </Badge>
                                                    ) : (
                                                        '—'
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {permission.roles_count}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex justify-end gap-1">
                                                        <Button
                                                            size="icon"
                                                            variant="outline"
                                                            title={
                                                                can(
                                                                    'permissions.update',
                                                                )
                                                                    ? 'Edit'
                                                                    : 'View'
                                                            }
                                                            onClick={() =>
                                                                open(permission)
                                                            }
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                            <span className="sr-only">
                                                                Edit{' '}
                                                                {
                                                                    permission.name
                                                                }
                                                            </span>
                                                        </Button>
                                                        {can(
                                                            'permissions.delete',
                                                        ) &&
                                                            !permission.is_system && (
                                                                <Button
                                                                    size="icon"
                                                                    variant="destructive"
                                                                    title="Delete"
                                                                    onClick={() =>
                                                                        setDeleting(
                                                                            permission,
                                                                        )
                                                                    }
                                                                >
                                                                    <Trash2 className="h-4 w-4" />
                                                                    <span className="sr-only">
                                                                        Delete{' '}
                                                                        {
                                                                            permission.name
                                                                        }
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

                        <DataTableFooter
                            page={permissions}
                            noun="permission(s)"
                        />
                    </CardContent>
                </Card>
            </div>

            <Dialog
                open={editing !== null}
                onOpenChange={(value) => !value && setEditing(null)}
            >
                <DialogContent className="sm:max-w-[520px]">
                    <form onSubmit={submit}>
                        <DialogHeader>
                            <DialogTitle>
                                {editing === 'new'
                                    ? 'New permission'
                                    : 'Permission'}
                            </DialogTitle>
                            <DialogDescription>
                                {editing !== 'new' && editing?.is_system
                                    ? 'The application checks this permission by name, so only its module and description can change.'
                                    : 'Use lowercase words joined by dots, e.g. reports.view.'}
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-4 py-4">
                            <div className="grid gap-2">
                                <Label htmlFor="permission-name">Name</Label>
                                <Input
                                    id="permission-name"
                                    className="font-mono"
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                    disabled={
                                        !canEdit ||
                                        (editing !== 'new' &&
                                            editing?.is_system)
                                    }
                                />
                                <InputError message={form.errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="permission-module">
                                    Module
                                </Label>
                                <Input
                                    id="permission-module"
                                    list="permission-modules"
                                    value={form.data.module}
                                    onChange={(event) =>
                                        form.setData(
                                            'module',
                                            event.target.value,
                                        )
                                    }
                                    disabled={!canEdit}
                                />
                                <datalist id="permission-modules">
                                    {modules.map((module) => (
                                        <option key={module} value={module} />
                                    ))}
                                </datalist>
                                <InputError message={form.errors.module} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="permission-description">
                                    Description
                                </Label>
                                <Textarea
                                    id="permission-description"
                                    rows={3}
                                    value={form.data.description}
                                    onChange={(event) =>
                                        form.setData(
                                            'description',
                                            event.target.value,
                                        )
                                    }
                                    disabled={!canEdit}
                                />
                                <InputError message={form.errors.description} />
                            </div>
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setEditing(null)}
                            >
                                {canEdit ? 'Cancel' : 'Close'}
                            </Button>
                            {canEdit && (
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    {form.processing
                                        ? 'Saving...'
                                        : editing === 'new'
                                          ? 'Create permission'
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
                title="Delete permission"
                description={
                    <>
                        Delete <strong>{deleting?.name}</strong>? Permissions
                        still in a role cannot be deleted.
                    </>
                }
                confirmLabel="Delete permission"
                variant="destructive"
                onConfirm={() => {
                    if (deleting) {
                        router.delete(
                            permissionRoutes.destroy.url(deleting.id),
                            {
                                preserveScroll: true,
                                onError: (errors) =>
                                    toast.error(
                                        Object.values(errors)[0] ??
                                            'Could not delete.',
                                    ),
                            },
                        );
                    }

                    setDeleting(null);
                }}
            />
        </>
    );
}

PermissionsIndex.layout = {
    breadcrumbs: [{ title: 'Permissions', href: permissionRoutes.index() }],
};
