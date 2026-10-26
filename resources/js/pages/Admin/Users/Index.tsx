import { Head, router, useForm } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    Ban,
    Check,
    Eye,
    Pencil,
    Search,
    ShieldAlert,
    ShieldCheck,
    Trash2,
    UserCheck,
    UserX,
} from 'lucide-react';
import { type ChangeEvent, type FormEvent, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
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
import {
    Field,
    FieldContent,
    FieldDescription,
    FieldGroup,
    FieldLabel,
    FieldTitle,
} from '@/components/ui/field';
import { Switch } from '@/components/ui/switch';
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

import { promiseAction } from '@/lib/promise-action';
import { UserAvatar } from '@/components/user-avatar';
import { UserStatusBadge } from '@/components/user-status-badge';
import { ConfirmDialog } from '@/components/confirm-dialog';
import admin from '@/routes/admin';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';

type UserStatus = 'pending' | 'approved' | 'rejected';

/** "super_admin" → "Super Admin". */
function roleLabel(name: string): string {
    return name
        .split(/[_\s-]+/)
        .filter(Boolean)
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}
type DisplayStatus = 'pending' | 'active' | 'inactive' | 'rejected';

interface Reviewer {
    id: number;
    name: string;
}

interface User {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    avatar: string | null;
    status: UserStatus;
    display_status: DisplayStatus;
    is_active: boolean;
    must_change_credentials: boolean;
    reviewed_by: number | null;
    reviewed_at: string | null;
    rejection_reason: string | null;
    reviewer: Reviewer | null;
    created_at: string | null;
    roles: { id: number; name: string }[];
    role: string | null;
    /** Superadmin accounts can never be deleted. */
    is_protected: boolean;
    /** Server decides (Phase 4, F08): outranks the account and it isn't you. */
    can_assign_roles: boolean;
    can_approve: boolean;
}

interface AssignableRole {
    id: number;
    name: string;
    label: string;
    /** False when the role has permissions you don't have, or is Superadmin/Admin. */
    assignable: boolean;
}

interface PaginatedUsers {
    data: User[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
    path: string;
    first_page_url: string | null;
    prev_page_url: string | null;
    next_page_url: string | null;
    last_page_url: string | null;
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
}

interface Props {
    users: PaginatedUsers;
    assignableRoles: AssignableRole[];
    filters: {
        search?: string;
        status?: string;
        is_active?: string;
        per_page?: string;
    };
}

export default function Index({ users, filters, assignableRoles = [] }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [editUser, setEditUser] = useState<User | null>(null);
    const [avatarPreview, setAvatarPreview] = useState<string | null>(null);
    const [deleteUser, setDeleteUser] = useState<User | null>(null);
    const [viewUser, setViewUser] = useState<User | null>(null);
    const [toggleTarget, setToggleTarget] = useState<User | null>(null);
    const [rejectTarget, setRejectTarget] = useState<User | null>(null);
    const [rejectReason, setRejectReason] = useState('');
    const [rolesUser, setRolesUser] = useState<User | null>(null);
    const [selectedRoles, setSelectedRoles] = useState<string[]>([]);
    const [savingRoles, setSavingRoles] = useState(false);

    const openRoles = (user: User) => {
        setRolesUser(user);
        setSelectedRoles(user.roles.map((r) => r.name));
    };

    const toggleRole = (name: string, on: boolean) =>
        setSelectedRoles((current) =>
            on
                ? [...new Set([...current, name])]
                : current.filter((r) => r !== name),
        );

    const saveRoles = () => {
        if (!rolesUser) {
            return;
        }
        setSavingRoles(true);
        router.put(
            `/admin/users/${rolesUser.id}/roles`,
            { roles: selectedRoles },
            {
                preserveScroll: true,
                onSuccess: () => setRolesUser(null),
                onFinish: () => setSavingRoles(false),
            },
        );
    };

    const editForm = useForm({
        _method: 'put' as const,
        name: '',
        email: '',
        phone: '',
        is_active: true,
        must_change_credentials: false,
        avatar: null as File | null,
    });

    // Revoke blob preview URLs when they're replaced/unmounted.
    useEffect(() => {
        return () => {
            if (avatarPreview?.startsWith('blob:')) {
                URL.revokeObjectURL(avatarPreview);
            }
        };
    }, [avatarPreview]);

    const updateFilters = (
        next: Partial<{ search: string; status: string; per_page: string }>,
    ) => {
        router.get(
            '/admin/users',
            {
                search: (next.search ?? filters.search) || undefined,
                status: (next.status ?? filters.status) || undefined,
                is_active: filters.is_active || undefined,
                per_page: next.per_page ?? filters.per_page ?? undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    // Live (debounced) search.
    useEffect(() => {
        const timeout = setTimeout(() => {
            if (search !== (filters.search ?? '')) {
                updateFilters({ search });
            }
        }, 350);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const filterStatus = (value: string) => {
        updateFilters({ status: value === 'all' ? '' : value });
    };

    const setPerPage = (value: string) => {
        updateFilters({ per_page: value });
    };

    const goToPage = (url: string | null) => {
        if (!url) {
            return;
        }

        router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    const openEditModal = (user: User) => {
        setEditUser(user);
        setAvatarPreview(user.avatar);

        editForm.setData({
            _method: 'put',
            name: user.name,
            email: user.email,
            phone: user.phone ?? '',
            is_active: user.is_active,
            must_change_credentials: user.must_change_credentials,
            avatar: null,
        });
    };

    const closeEditModal = () => {
        setEditUser(null);
        setAvatarPreview(null);
        editForm.reset();
        editForm.clearErrors();
    };

    const onAvatarChange = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0] ?? null;
        editForm.setData('avatar', file);
        setAvatarPreview(
            file ? URL.createObjectURL(file) : (editUser?.avatar ?? null),
        );
    };

    const submitEdit = (event: FormEvent) => {
        event.preventDefault();

        if (!editUser) {
            return;
        }

        const promise = new Promise<void>((resolve, reject) => {
            // Files need a POST body; Laravel reads it as the PUT route.
            editForm.transform((data) => ({ ...data, _method: 'put' }));
            editForm.post(`/admin/users/${editUser.id}`, {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    closeEditModal();
                    resolve();
                },
                onError: (errors) => reject(errors),
            });
        });

        toast.promise(promise, {
            loading: 'Saving changes...',
            success: `${editUser.name} has been updated.`,
            error: (errors: Record<string, string>) =>
                Object.values(errors ?? {})[0] ??
                'Please check the form for errors.',
        });
    };

    const toggleActive = (user: User) => {
        const url = user.is_active
            ? `/admin/users/${user.id}/deactivate`
            : `/admin/users/${user.id}/activate`;

        promiseAction(url, {
            loading: user.is_active
                ? 'Deactivating user...'
                : 'Activating user...',
            success: user.is_active
                ? `${user.name} has been deactivated.`
                : `${user.name} has been activated.`,
        });
    };

    const approveUser = (user: User) =>
        promiseAction(`/admin/users/${user.id}/approve`, {
            loading: 'Approving account...',
            success: `${user.name} is approved and has been emailed.`,
        });

    const rejectUser = (event: FormEvent) => {
        event.preventDefault();

        if (!rejectTarget) {
            return;
        }

        promiseAction(`/admin/users/${rejectTarget.id}/reject`, {
            data: { reason: rejectReason },
            loading: 'Saving...',
            success: `${rejectTarget.name} was turned down and has been emailed.`,
            error: 'Give a reason.',
        }).then(
            () => {
                setRejectTarget(null);
                setRejectReason('');
            },
            // The toast already shows the error; keep the dialog open.
            () => undefined,
        );
    };

    const confirmToggle = () => {
        if (!toggleTarget) {
            return;
        }

        toggleActive(toggleTarget);
        setToggleTarget(null);
    };

    const deleteAccount = () => {
        if (!deleteUser) {
            return;
        }

        promiseAction(`/admin/users/${deleteUser.id}`, {
            method: 'delete',
            loading: 'Deleting user...',
            success: `${deleteUser.name} has been deleted.`,
        });

        setDeleteUser(null);
    };

    return (
        <>
            <Head title="User Management" />

            <div className="flex flex-1 flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            User Management
                        </h1>

                        <p className="text-muted-foreground">
                            Manage user accounts, approvals, and access.
                        </p>
                    </div>
                </div>

                {/* Main Card */}
                <Card>
                    <CardHeader>
                        <CardTitle>Users</CardTitle>
                    </CardHeader>

                    <CardContent>
                        {/* Filters */}
                        <div className="mb-6 flex flex-col gap-3 md:flex-row">
                            <div className="relative flex-1">
                                <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />

                                <Input
                                    placeholder="Search by name, email, or phone..."
                                    value={search}
                                    className="w-full max-w-sm pl-9"
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                />
                            </div>

                            <Select
                                value={filters.status || 'all'}
                                onValueChange={filterStatus}
                            >
                                <SelectTrigger className="w-full md:w-[180px]">
                                    <SelectValue placeholder="Status" />
                                </SelectTrigger>

                                <SelectContent>
                                    <SelectItem value="all">
                                        All Statuses
                                    </SelectItem>

                                    <SelectItem value="pending">
                                        Pending
                                    </SelectItem>

                                    <SelectItem value="approved">
                                        Approved
                                    </SelectItem>

                                    <SelectItem value="rejected">
                                        Rejected
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Table */}
                        <div className="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>User</TableHead>
                                        <TableHead>Phone</TableHead>
                                        <TableHead>Role</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Reviewed By</TableHead>
                                        <TableHead className="text-right">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>

                                <TableBody>
                                    {users.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={5}
                                                className="h-32 text-center"
                                            >
                                                No users found.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        users.data.map((user) => (
                                            <TableRow key={user.id}>
                                                <TableCell>
                                                    <div className="flex items-center gap-3">
                                                        <UserAvatar
                                                            name={user.name}
                                                            avatar={user.avatar}
                                                        />

                                                        <div>
                                                            <div className="font-medium">
                                                                {user.name}
                                                            </div>

                                                            <div className="text-sm text-muted-foreground">
                                                                {user.email}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </TableCell>

                                                <TableCell>
                                                    {user.phone || '—'}
                                                </TableCell>
                                                <TableCell>
                                                    {user.roles.length > 0 ? (
                                                        <div className="flex flex-wrap gap-1">
                                                            {user.roles.map(
                                                                (role) => (
                                                                    <Badge
                                                                        key={
                                                                            role.id
                                                                        }
                                                                        variant="outline"
                                                                        className="bg-sky-50 text-sky-700 dark:bg-sky-950 dark:text-sky-300"
                                                                    >
                                                                        {roleLabel(
                                                                            role.name,
                                                                        )}
                                                                    </Badge>
                                                                ),
                                                            )}
                                                        </div>
                                                    ) : (
                                                        <span className="text-muted-foreground">
                                                            —
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <UserStatusBadge
                                                        status={
                                                            user.display_status
                                                        }
                                                    />
                                                </TableCell>

                                                <TableCell>
                                                    {user.reviewer?.name || '—'}
                                                </TableCell>

                                                <TableCell>
                                                    <div className="flex justify-end gap-1">
                                                        <Button
                                                            size="icon"
                                                            title="view"
                                                            variant="outline"
                                                            onClick={() =>
                                                                setViewUser(
                                                                    user,
                                                                )
                                                            }
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Button>

                                                        <Button
                                                            size="icon"
                                                            variant="outline"
                                                            title="edit"
                                                            onClick={() =>
                                                                openEditModal(
                                                                    user,
                                                                )
                                                            }
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>

                                                        {user.can_assign_roles && (
                                                            <Button
                                                                size="icon"
                                                                variant="outline"
                                                                title="roles"
                                                                onClick={() =>
                                                                    openRoles(
                                                                        user,
                                                                    )
                                                                }
                                                            >
                                                                <ShieldCheck className="h-4 w-4" />
                                                            </Button>
                                                        )}

                                                        {user.can_approve &&
                                                            user.status !==
                                                                'approved' && (
                                                                <Button
                                                                    size="icon"
                                                                    title="approve"
                                                                    aria-label={`Approve ${user.name}`}
                                                                    onClick={() =>
                                                                        approveUser(
                                                                            user,
                                                                        )
                                                                    }
                                                                >
                                                                    <Check className="h-4 w-4" />
                                                                </Button>
                                                            )}

                                                        {user.can_approve &&
                                                            user.status ===
                                                                'pending' && (
                                                                <Button
                                                                    size="icon"
                                                                    variant="outline"
                                                                    title="reject"
                                                                    aria-label={`Reject ${user.name}`}
                                                                    onClick={() =>
                                                                        setRejectTarget(
                                                                            user,
                                                                        )
                                                                    }
                                                                >
                                                                    <Ban className="h-4 w-4" />
                                                                </Button>
                                                            )}

                                                        {user.status ===
                                                            'approved' &&
                                                            !user.is_protected && (
                                                            <Button
                                                                size="icon"
                                                                variant="outline"
                                                                title={
                                                                    user.is_active
                                                                        ? 'deactivate'
                                                                        : 'activate'
                                                                }
                                                                onClick={() =>
                                                                    setToggleTarget(
                                                                        user,
                                                                    )
                                                                }
                                                            >
                                                                {user.is_active ? (
                                                                    <UserX className="h-4 w-4" />
                                                                ) : (
                                                                    <UserCheck className="h-4 w-4" />
                                                                )}
                                                            </Button>
                                                        )}

                                                        {user.is_protected ? (
                                                            <Badge
                                                                variant="outline"
                                                                className="gap-1"
                                                                title="Super admin accounts cannot be deactivated or deleted"
                                                            >
                                                                <ShieldAlert className="h-3 w-3" />
                                                                Protected
                                                            </Badge>
                                                        ) : (
                                                            <Button
                                                                size="icon"
                                                                title="delete"
                                                                variant="destructive"
                                                                onClick={() =>
                                                                    setDeleteUser(
                                                                        user,
                                                                    )
                                                                }
                                                            >
                                                                <Trash2 className="h-4 w-4" />
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

                        {/* Pagination */}
                        {/* Pagination */}
                        <div className="mt-6 flex items-center justify-between px-2">
                            <div className="flex-1 text-sm text-muted-foreground">
                                Showing {users.from ?? 0} to {users.to ?? 0} of{' '}
                                {users.total} user(s).
                            </div>

                            <div className="flex items-center space-x-2">
                                <Button
                                    variant="outline"
                                    size="icon"
                                    className="hidden size-8 lg:flex"
                                    onClick={() =>
                                        goToPage(users.first_page_url)
                                    }
                                    disabled={!users.prev_page_url}
                                >
                                    <span className="sr-only">
                                        Go to first page
                                    </span>
                                    <ChevronsLeft className="h-4 w-4" />
                                </Button>

                                <Button
                                    variant="outline"
                                    size="icon"
                                    className="size-8"
                                    onClick={() =>
                                        goToPage(users.prev_page_url)
                                    }
                                    disabled={!users.prev_page_url}
                                >
                                    <span className="sr-only">
                                        Go to previous page
                                    </span>
                                    <ChevronLeft className="h-4 w-4" />
                                </Button>

                                <Button
                                    variant="outline"
                                    size="icon"
                                    className="size-8"
                                    onClick={() =>
                                        goToPage(users.next_page_url)
                                    }
                                    disabled={!users.next_page_url}
                                >
                                    <span className="sr-only">
                                        Go to next page
                                    </span>
                                    <ChevronRight className="h-4 w-4" />
                                </Button>

                                <Button
                                    variant="outline"
                                    size="icon"
                                    className="hidden size-8 lg:flex"
                                    onClick={() =>
                                        goToPage(users.last_page_url)
                                    }
                                    disabled={!users.next_page_url}
                                >
                                    <span className="sr-only">
                                        Go to last page
                                    </span>
                                    <ChevronsRight className="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Roles Modal (Phase 4, F08): the server re-checks every change. */}
            <Dialog
                open={!!rolesUser}
                onOpenChange={(open) => !open && setRolesUser(null)}
            >
                <DialogContent className="sm:max-w-[440px]">
                    <DialogHeader>
                        <DialogTitle>Roles for {rolesUser?.name}</DialogTitle>
                        <DialogDescription>
                            Roles you can't give are greyed out: they include
                            permissions you don't have, or only the Super Admin
                            can give them.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-3 py-2">
                        {assignableRoles.map((role) => {
                            const checked = selectedRoles.includes(role.name);
                            const locked = !role.assignable;

                            return (
                                <label
                                    key={role.id}
                                    className={`flex items-center gap-3 text-sm ${locked ? 'opacity-50' : ''}`}
                                >
                                    <Checkbox
                                        checked={checked}
                                        disabled={locked}
                                        onCheckedChange={(value) =>
                                            toggleRole(
                                                role.name,
                                                value === true,
                                            )
                                        }
                                    />
                                    <span>{role.label}</span>
                                </label>
                            );
                        })}
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setRolesUser(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            onClick={saveRoles}
                            disabled={savingRoles || selectedRoles.length === 0}
                        >
                            {savingRoles ? 'Saving...' : 'Save Roles'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Edit User Modal */}
            <Dialog
                open={!!editUser}
                onOpenChange={(open) => !open && closeEditModal()}
            >
                <DialogContent className="sm:max-w-[520px]">
                    <DialogHeader>
                        <DialogTitle>Edit User</DialogTitle>

                        <DialogDescription>
                            Update this user's account information and access
                            settings.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submitEdit}>
                        <div className="grid gap-5 py-4">
                            <div className="flex items-center gap-4">
                                <Avatar className="h-16 w-16">
                                    <AvatarImage
                                        src={avatarPreview ?? undefined}
                                        alt={editForm.data.name}
                                    />
                                    <AvatarFallback>
                                        {editForm.data.name
                                            .split(' ')
                                            .map((part) => part[0])
                                            .slice(0, 2)
                                            .join('')
                                            .toUpperCase()}
                                    </AvatarFallback>
                                </Avatar>

                                <div className="grid flex-1 gap-1.5">
                                    <Label htmlFor="avatar">
                                        Profile Photo
                                    </Label>

                                    <Input
                                        id="avatar"
                                        type="file"
                                        accept="image/*"
                                        onChange={onAvatarChange}
                                    />

                                    {editForm.errors.avatar && (
                                        <p className="text-sm text-red-500">
                                            {editForm.errors.avatar}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="name">Full Name</Label>

                                <Input
                                    id="name"
                                    value={editForm.data.name}
                                    onChange={(event) =>
                                        editForm.setData(
                                            'name',
                                            event.target.value,
                                        )
                                    }
                                />

                                {editForm.errors.name && (
                                    <p className="text-sm text-red-500">
                                        {editForm.errors.name}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email Address</Label>

                                <Input
                                    id="email"
                                    type="email"
                                    value={editForm.data.email}
                                    onChange={(event) =>
                                        editForm.setData(
                                            'email',
                                            event.target.value,
                                        )
                                    }
                                />

                                {editForm.errors.email && (
                                    <p className="text-sm text-red-500">
                                        {editForm.errors.email}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="phone">Phone</Label>

                                <Input
                                    id="phone"
                                    value={editForm.data.phone}
                                    onChange={(event) =>
                                        editForm.setData(
                                            'phone',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>

                            <FieldGroup>
                                <FieldLabel htmlFor="is_active">
                                    <Field orientation="horizontal">
                                        <FieldContent>
                                            <FieldTitle>
                                                Account Active
                                            </FieldTitle>
                                            <FieldDescription>
                                                Allow this user to access the
                                                system.
                                            </FieldDescription>
                                        </FieldContent>

                                        <Switch
                                            id="is_active"
                                            checked={editForm.data.is_active}
                                            onCheckedChange={(checked) =>
                                                editForm.setData(
                                                    'is_active',
                                                    checked,
                                                )
                                            }
                                        />
                                    </Field>
                                </FieldLabel>

                                <FieldLabel htmlFor="must_change_credentials">
                                    <Field orientation="horizontal">
                                        <FieldContent>
                                            <FieldTitle>
                                                Require Credential Change
                                            </FieldTitle>
                                            <FieldDescription>
                                                Require the user to change their
                                                credentials.
                                            </FieldDescription>
                                        </FieldContent>

                                        <Switch
                                            id="must_change_credentials"
                                            checked={
                                                editForm.data
                                                    .must_change_credentials
                                            }
                                            onCheckedChange={(checked) =>
                                                editForm.setData(
                                                    'must_change_credentials',
                                                    checked,
                                                )
                                            }
                                        />
                                    </Field>
                                </FieldLabel>
                            </FieldGroup>
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={closeEditModal}
                            >
                                Cancel
                            </Button>

                            <Button
                                type="submit"
                                disabled={editForm.processing}
                            >
                                {editForm.processing
                                    ? 'Saving...'
                                    : 'Save Changes'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Activate / Deactivate Confirmation */}
            <ConfirmDialog
                open={!!toggleTarget}
                onOpenChange={(open) => !open && setToggleTarget(null)}
                title={
                    toggleTarget?.is_active
                        ? 'Deactivate User'
                        : 'Activate User'
                }
                description={
                    toggleTarget?.is_active ? (
                        <>
                            Are you sure you want to deactivate{' '}
                            <strong>{toggleTarget?.name}</strong>? They will
                            immediately lose access to the application.
                        </>
                    ) : (
                        <>
                            Are you sure you want to activate{' '}
                            <strong>{toggleTarget?.name}</strong>? They will
                            regain access to the application.
                        </>
                    )
                }
                confirmLabel={
                    toggleTarget?.is_active ? 'Deactivate' : 'Activate'
                }
                variant={toggleTarget?.is_active ? 'destructive' : 'default'}
                onConfirm={confirmToggle}
            />

            {/* View Details */}
            <Dialog
                open={!!viewUser}
                onOpenChange={(open) => !open && setViewUser(null)}
            >
                <DialogContent className="sm:max-w-[480px]">
                    <DialogHeader>
                        <div className="flex items-center gap-3">
                            <UserAvatar
                                name={viewUser?.name ?? ''}
                                avatar={viewUser?.avatar ?? null}
                            />
                            <div>
                                <DialogTitle>{viewUser?.name}</DialogTitle>
                                <DialogDescription>
                                    {viewUser?.email}
                                </DialogDescription>
                            </div>
                        </div>
                    </DialogHeader>
                    {viewUser && (
                        <dl className="grid grid-cols-[8rem_1fr] gap-x-4 gap-y-3 py-2 text-sm">
                            <dt className="text-muted-foreground">Phone</dt>
                            <dd>{viewUser.phone || '—'}</dd>
                            <dt className="text-muted-foreground">Status</dt>
                            <dd>
                                <UserStatusBadge
                                    status={viewUser.display_status}
                                />
                            </dd>
                            <dt className="text-muted-foreground">Roles</dt>
                            <dd className="flex flex-wrap gap-1">
                                {viewUser.roles.length > 0
                                    ? viewUser.roles.map((role) => (
                                          <Badge
                                              key={role.id}
                                              variant="outline"
                                          >
                                              {roleLabel(role.name)}
                                          </Badge>
                                      ))
                                    : '—'}
                            </dd>
                            <dt className="text-muted-foreground">Joined</dt>
                            <dd>
                                {viewUser.created_at
                                    ? new Date(
                                          viewUser.created_at,
                                      ).toLocaleDateString()
                                    : '—'}
                            </dd>
                            <dt className="text-muted-foreground">
                                Credential change
                            </dt>
                            <dd>
                                {viewUser.must_change_credentials
                                    ? 'Required at next sign-in'
                                    : 'Not required'}
                            </dd>
                        </dl>
                    )}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setViewUser(null)}
                        >
                            Close
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Confirmation */}
            <Dialog
                open={!!rejectTarget}
                onOpenChange={(open) => !open && setRejectTarget(null)}
            >
                <DialogContent>
                    <form onSubmit={rejectUser} className="space-y-4">
                        <DialogHeader>
                            <DialogTitle>
                                Turn down {rejectTarget?.name}
                            </DialogTitle>
                            <DialogDescription>
                                We email them this reason. They cannot use their
                                account until you approve it.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-2">
                            <Label htmlFor="reject-reason">Reason</Label>
                            <Input
                                id="reject-reason"
                                value={rejectReason}
                                onChange={(event) =>
                                    setRejectReason(event.target.value)
                                }
                                maxLength={500}
                                required
                            />
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setRejectTarget(null)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" variant="destructive">
                                Turn down
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={!!deleteUser}
                onOpenChange={(open) => !open && setDeleteUser(null)}
                title="Delete User"
                description={
                    <>
                        Are you sure you want to delete{' '}
                        <strong>{deleteUser?.name}</strong>? The account will be
                        moved to the trash.
                    </>
                }
                confirmLabel="Delete User"
                variant="destructive"
                onConfirm={deleteAccount}
            />
        </>
    );
}

Index.layout = {
    breadcrumbs: [
        {
            title: 'User Management',
            href: admin,
        },
    ],
};
