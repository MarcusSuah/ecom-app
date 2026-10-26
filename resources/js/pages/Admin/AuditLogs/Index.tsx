import { Head, router } from '@inertiajs/react';
import { FilePen, FilePlus, FileX, Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { DataTableFooter } from '@/components/data-table-footer';
import { DetailDialog } from '@/components/detail-dialog';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import auditRoutes from '@/routes/admin/audit-logs';
import type { Paginated } from '@/types';

type Values = Record<string, unknown> | null;

type Log = {
    id: string;
    action: 'create' | 'update' | 'delete' | string;
    module: string;
    record_id: string | null;
    description: string | null;
    old_values: Values;
    new_values: Values;
    performed_by: string;
    role: string | null;
    ip_address: string | null;
    user_agent: string | null;
    created_at: string | null;
};

type Filters = {
    search: string;
    action: string;
    module: string;
    from: string;
    to: string;
};

const actions = {
    create: {
        label: 'Created',
        icon: FilePlus,
        className:
            'border-emerald-600/40 text-emerald-700 dark:text-emerald-300',
    },
    update: {
        label: 'Updated',
        icon: FilePen,
        className: 'border-sky-600/40 text-sky-700 dark:text-sky-300',
    },
    delete: {
        label: 'Deleted',
        icon: FileX,
        className: 'border-red-600/40 text-red-700 dark:text-red-300',
    },
} as const;

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';

const show = (value: unknown) =>
    value === null || value === undefined || value === ''
        ? '—'
        : typeof value === 'object'
          ? JSON.stringify(value)
          : String(value);

const dateTime = (value: string | null) =>
    value ? new Date(value).toLocaleString() : '—';

export default function AuditLogsIndex({
    logs,
    modules,
    filters,
}: {
    logs: Paginated<Log>;
    modules: string[];
    filters: Filters;
}) {
    const [search, setSearch] = useState(filters.search);
    const [viewing, setViewing] = useState<Log | null>(null);

    const apply = (next: Partial<Filters>) =>
        router.get(
            auditRoutes.index.url(),
            Object.fromEntries(
                Object.entries({ ...filters, ...next }).filter(
                    ([, value]) => value !== '',
                ),
            ),
            { preserveState: true, preserveScroll: true, replace: true },
        );

    useEffect(() => {
        const timeout = setTimeout(
            () => search !== filters.search && apply({ search }),
            350,
        );

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const keys = viewing
        ? [
              ...new Set([
                  ...Object.keys(viewing.old_values ?? {}),
                  ...Object.keys(viewing.new_values ?? {}),
              ]),
          ]
        : [];

    return (
        <>
            <Head title="Audit log" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Audit log
                    </h1>
                    <p className="text-muted-foreground">
                        Every change people made on this marketplace, with the
                        values before and after.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Changes</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="mb-6 grid gap-3 md:grid-cols-[1fr_10rem_12rem_10rem_10rem]">
                            <div className="relative">
                                <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    placeholder="Search description, person or record ID..."
                                    value={search}
                                    className="pl-9"
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    aria-label="Search the audit log"
                                />
                            </div>
                            <select
                                aria-label="Action"
                                value={filters.action}
                                onChange={(event) =>
                                    apply({ action: event.target.value })
                                }
                                className={selectClass}
                            >
                                <option value="">All actions</option>
                                <option value="create">Created</option>
                                <option value="update">Updated</option>
                                <option value="delete">Deleted</option>
                            </select>
                            <select
                                aria-label="Record type"
                                value={filters.module}
                                onChange={(event) =>
                                    apply({ module: event.target.value })
                                }
                                className={selectClass}
                            >
                                <option value="">All records</option>
                                {modules.map((module) => (
                                    <option key={module} value={module}>
                                        {module.replaceAll('_', ' ')}
                                    </option>
                                ))}
                            </select>
                            <div className="grid gap-1">
                                <Label htmlFor="audit-from" className="sr-only">
                                    From
                                </Label>
                                <Input
                                    id="audit-from"
                                    type="date"
                                    value={filters.from}
                                    onChange={(event) =>
                                        apply({ from: event.target.value })
                                    }
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="audit-to" className="sr-only">
                                    To
                                </Label>
                                <Input
                                    id="audit-to"
                                    type="date"
                                    value={filters.to}
                                    onChange={(event) =>
                                        apply({ to: event.target.value })
                                    }
                                />
                            </div>
                        </div>

                        <div className="overflow-x-auto rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>When</TableHead>
                                        <TableHead>Who</TableHead>
                                        <TableHead>Action</TableHead>
                                        <TableHead>What</TableHead>
                                        <TableHead>Record</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {logs.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={5}
                                                className="h-32 text-center"
                                            >
                                                No changes match.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        logs.data.map((log) => {
                                            const action =
                                                actions[
                                                    log.action as keyof typeof actions
                                                ];

                                            return (
                                                <TableRow
                                                    key={log.id}
                                                    className="cursor-pointer"
                                                    onClick={() =>
                                                        setViewing(log)
                                                    }
                                                >
                                                    <TableCell className="text-sm whitespace-nowrap">
                                                        {dateTime(
                                                            log.created_at,
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="font-medium">
                                                            {log.performed_by}
                                                        </div>
                                                        {log.role && (
                                                            <div className="text-xs text-muted-foreground">
                                                                {log.role}
                                                            </div>
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        {action ? (
                                                            <Badge
                                                                variant="outline"
                                                                className={cn(
                                                                    'gap-1',
                                                                    action.className,
                                                                )}
                                                            >
                                                                <action.icon
                                                                    className="h-3 w-3"
                                                                    aria-hidden="true"
                                                                />
                                                                {action.label}
                                                            </Badge>
                                                        ) : (
                                                            log.action
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="max-w-md">
                                                        {log.description}
                                                    </TableCell>
                                                    <TableCell className="text-xs text-muted-foreground">
                                                        {log.module.replaceAll(
                                                            '_',
                                                            ' ',
                                                        )}
                                                        {log.record_id
                                                            ? ` #${log.record_id}`
                                                            : ''}
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                        <DataTableFooter page={logs} noun="change(s)" />
                    </CardContent>
                </Card>
            </div>

            <DetailDialog
                open={viewing !== null}
                onOpenChange={(open) => !open && setViewing(null)}
                title={viewing?.description}
                description={
                    viewing
                        ? `${viewing.performed_by} · ${dateTime(viewing.created_at)}`
                        : undefined
                }
                rows={
                    viewing
                        ? [
                              {
                                  label: 'Record',
                                  value: `${viewing.module}${viewing.record_id ? ` #${viewing.record_id}` : ''}`,
                              },
                              { label: 'Role', value: viewing.role },
                              {
                                  label: 'IP address',
                                  value: viewing.ip_address,
                              },
                              { label: 'Browser', value: viewing.user_agent },
                          ]
                        : []
                }
            >
                {keys.length > 0 && (
                    <div className="max-h-72 overflow-auto rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Field</TableHead>
                                    <TableHead>Before</TableHead>
                                    <TableHead>After</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {keys.map((key) => (
                                    <TableRow key={key}>
                                        <TableCell className="font-mono text-xs">
                                            {key}
                                        </TableCell>
                                        <TableCell className="max-w-48 text-xs break-words text-red-700 dark:text-red-300">
                                            {show(viewing?.old_values?.[key])}
                                        </TableCell>
                                        <TableCell className="max-w-48 text-xs break-words text-emerald-700 dark:text-emerald-300">
                                            {show(viewing?.new_values?.[key])}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </DetailDialog>
        </>
    );
}

AuditLogsIndex.layout = {
    breadcrumbs: [{ title: 'Audit log', href: auditRoutes.index() }],
};
