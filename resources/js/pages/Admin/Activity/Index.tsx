import { Form, Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Ban,
    Globe,
    LogIn,
    LogOut,
    Monitor,
    MousePointerClick,
    Search,
    ShieldOff,
    Smartphone,
    Tablet,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { DataTableFooter } from '@/components/data-table-footer';
import { DetailDialog } from '@/components/detail-dialog';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
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
import activityRoutes from '@/routes/admin/activity';
import type { Paginated } from '@/types';

type Activity = {
    id: number;
    event: string;
    description: string | null;
    user: {
        id: number;
        name: string;
        email: string;
        is_blocked: boolean;
    } | null;
    email: string | null;
    method: string | null;
    url: string | null;
    route_name: string | null;
    ip_address: string | null;
    ip_blocked: boolean;
    device: string | null;
    browser: string | null;
    platform: string | null;
    location: string | null;
    is_suspicious: boolean;
    created_at: string | null;
};

type BlockedIp = {
    id: number;
    ip_address: string;
    reason: string | null;
    blocked_by: string | null;
    expires_at: string | null;
    created_at: string | null;
};

type Props = {
    activities: Paginated<Activity>;
    events: string[];
    blockedIps: BlockedIp[];
    filters: { search: string; event: string; suspicious: boolean };
};

const eventIcons: Record<string, typeof LogIn> = {
    login: LogIn,
    logout: LogOut,
    login_failed: AlertTriangle,
    page_view: MousePointerClick,
    blocked: Ban,
};
const deviceIcons: Record<string, typeof Monitor> = {
    Mobile: Smartphone,
    Tablet,
};

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';

const dateTime = (value: string | null) =>
    value ? new Date(value).toLocaleString() : '—';

export default function ActivityIndex({
    activities,
    events,
    blockedIps,
    filters,
}: Props) {
    const { can } = usePermissions();
    const [search, setSearch] = useState(filters.search);
    const [viewing, setViewing] = useState<Activity | null>(null);
    const [blocking, setBlocking] = useState<Activity | null>(null);

    const apply = (next: Partial<Props['filters']>) =>
        router.get(
            activityRoutes.index.url(),
            Object.fromEntries(
                Object.entries({ ...filters, ...next }).filter(
                    ([, value]) => value !== '' && value !== false,
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

    const onError = (errors: Record<string, string>) =>
        toast.error(Object.values(errors)[0] ?? 'That did not work.');

    return (
        <>
            <Head title="Activity log" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Activity log
                    </h1>
                    <p className="text-muted-foreground">
                        Sign-ins, failed attempts, pages opened and changes
                        submitted by members, with device and network.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Activity</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="mb-6 flex flex-col gap-3 md:flex-row md:items-center">
                            <div className="relative flex-1">
                                <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    placeholder="Search name, email or IP address..."
                                    value={search}
                                    className="w-full max-w-sm pl-9"
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    aria-label="Search activity"
                                />
                            </div>
                            <select
                                aria-label="Event"
                                value={filters.event}
                                onChange={(event) =>
                                    apply({ event: event.target.value })
                                }
                                className={`${selectClass} md:w-48`}
                            >
                                <option value="">All events</option>
                                {events.map((event) => (
                                    <option key={event} value={event}>
                                        {event.replaceAll('_', ' ')}
                                    </option>
                                ))}
                            </select>
                            <label className="flex items-center gap-2 text-sm whitespace-nowrap">
                                <Switch
                                    checked={filters.suspicious}
                                    onCheckedChange={(checked) =>
                                        apply({ suspicious: checked })
                                    }
                                />
                                Suspicious only
                            </label>
                        </div>

                        <div className="overflow-x-auto rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>When</TableHead>
                                        <TableHead>Who</TableHead>
                                        <TableHead>Event</TableHead>
                                        <TableHead>Device</TableHead>
                                        <TableHead>Network</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {activities.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={5}
                                                className="h-32 text-center"
                                            >
                                                No activity matches.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        activities.data.map((activity) => {
                                            const EventIcon =
                                                eventIcons[activity.event] ??
                                                MousePointerClick;
                                            const DeviceIcon =
                                                deviceIcons[
                                                    activity.device ?? ''
                                                ] ?? Monitor;

                                            return (
                                                <TableRow
                                                    key={activity.id}
                                                    className="cursor-pointer"
                                                    onClick={() =>
                                                        setViewing(activity)
                                                    }
                                                >
                                                    <TableCell className="text-sm whitespace-nowrap">
                                                        {dateTime(
                                                            activity.created_at,
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="flex items-center gap-2 font-medium">
                                                            {activity.user
                                                                ?.name ??
                                                                activity.email ??
                                                                'Guest'}
                                                            {activity.user
                                                                ?.is_blocked && (
                                                                <Badge variant="destructive">
                                                                    Blocked
                                                                </Badge>
                                                            )}
                                                        </div>
                                                        {activity.user && (
                                                            <div className="text-xs text-muted-foreground">
                                                                {
                                                                    activity
                                                                        .user
                                                                        .email
                                                                }
                                                            </div>
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="flex items-center gap-2">
                                                            <EventIcon
                                                                className="h-4 w-4 text-muted-foreground"
                                                                aria-hidden="true"
                                                            />
                                                            <span className="text-sm">
                                                                {activity.description ??
                                                                    activity.event}
                                                            </span>
                                                            {activity.is_suspicious && (
                                                                <Badge
                                                                    variant="outline"
                                                                    className="border-amber-500/50 text-amber-700 dark:text-amber-300"
                                                                >
                                                                    Suspicious
                                                                </Badge>
                                                            )}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="text-sm">
                                                        <span className="inline-flex items-center gap-1.5">
                                                            <DeviceIcon
                                                                className="h-4 w-4 text-muted-foreground"
                                                                aria-hidden="true"
                                                            />
                                                            {[
                                                                activity.browser,
                                                                activity.platform,
                                                            ]
                                                                .filter(Boolean)
                                                                .join(' · ') ||
                                                                '—'}
                                                        </span>
                                                    </TableCell>
                                                    <TableCell className="text-sm">
                                                        <div className="font-mono text-xs">
                                                            {activity.ip_address ??
                                                                '—'}
                                                        </div>
                                                        {activity.location && (
                                                            <div className="text-xs text-muted-foreground">
                                                                {
                                                                    activity.location
                                                                }
                                                            </div>
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                        <DataTableFooter page={activities} noun="event(s)" />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Globe
                                className="size-4 text-primary"
                                aria-hidden="true"
                            />
                            Blocked IP addresses
                        </CardTitle>
                        <CardDescription>
                            Requests from these addresses are refused on every
                            marketplace.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {can('activity.manage') && (
                            <Form
                                {...activityRoutes.ips.block.form()}
                                resetOnSuccess
                                options={{ preserveScroll: true }}
                                className="grid gap-3 sm:grid-cols-[12rem_1fr_7rem_auto] sm:items-end"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <div className="grid gap-1.5">
                                            <Label htmlFor="ip_address">
                                                IP address
                                            </Label>
                                            <Input
                                                id="ip_address"
                                                name="ip_address"
                                                placeholder="203.0.113.7"
                                                required
                                            />
                                        </div>
                                        <div className="grid gap-1.5">
                                            <Label htmlFor="ip_reason">
                                                Reason
                                            </Label>
                                            <Input
                                                id="ip_reason"
                                                name="reason"
                                            />
                                        </div>
                                        <div className="grid gap-1.5">
                                            <Label htmlFor="ip_days">
                                                Days
                                            </Label>
                                            <Input
                                                id="ip_days"
                                                name="days"
                                                type="number"
                                                min={1}
                                                max={365}
                                                placeholder="Always"
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            variant="destructive"
                                            disabled={processing}
                                        >
                                            <Ban aria-hidden="true" />
                                            Block
                                        </Button>
                                        <InputError
                                            message={
                                                errors.ip_address ?? errors.days
                                            }
                                            className="sm:col-span-4"
                                        />
                                    </>
                                )}
                            </Form>
                        )}
                        {blockedIps.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No addresses are blocked.
                            </p>
                        ) : (
                            <ul className="divide-y rounded-md border">
                                {blockedIps.map((ip) => (
                                    <li
                                        key={ip.id}
                                        className="flex flex-wrap items-center justify-between gap-2 p-3 text-sm"
                                    >
                                        <div>
                                            <span className="font-mono">
                                                {ip.ip_address}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {ip.reason
                                                    ? ` · ${ip.reason}`
                                                    : ''}{' '}
                                                ·{' '}
                                                {ip.expires_at
                                                    ? `until ${dateTime(ip.expires_at)}`
                                                    : 'until unblocked'}
                                                {ip.blocked_by
                                                    ? ` · by ${ip.blocked_by}`
                                                    : ''}
                                            </span>
                                        </div>
                                        {can('activity.manage') && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    router.delete(
                                                        activityRoutes.ips.unblock.url(
                                                            ip.id,
                                                        ),
                                                        {
                                                            preserveScroll: true,
                                                            onError,
                                                        },
                                                    )
                                                }
                                            >
                                                <ShieldOff aria-hidden="true" />
                                                Unblock
                                            </Button>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>

            <DetailDialog
                open={viewing !== null}
                onOpenChange={(open) => !open && setViewing(null)}
                title={viewing?.description ?? viewing?.event}
                description={dateTime(viewing?.created_at ?? null)}
                rows={
                    viewing
                        ? [
                              {
                                  label: 'Member',
                                  value: viewing.user
                                      ? `${viewing.user.name} (${viewing.user.email})`
                                      : null,
                              },
                              {
                                  label: 'Email typed',
                                  value: !viewing.user ? viewing.email : null,
                              },
                              {
                                  label: 'Event',
                                  value: viewing.event.replaceAll('_', ' '),
                              },
                              {
                                  label: 'Page',
                                  value: viewing.url
                                      ? `${viewing.method ?? ''} ${viewing.url}`
                                      : null,
                              },
                              {
                                  label: 'Device',
                                  value: [
                                      viewing.device,
                                      viewing.browser,
                                      viewing.platform,
                                  ]
                                      .filter(Boolean)
                                      .join(' · '),
                              },
                              {
                                  label: 'IP address',
                                  value: viewing.ip_address
                                      ? `${viewing.ip_address}${viewing.ip_blocked ? ' (blocked)' : ''}`
                                      : null,
                              },
                              { label: 'Location', value: viewing.location },
                              {
                                  label: 'Suspicious',
                                  value: viewing.is_suspicious
                                      ? 'Yes: repeated failed sign-ins'
                                      : null,
                              },
                          ]
                        : []
                }
                actions={
                    viewing &&
                    can('activity.manage') && (
                        <>
                            {viewing.user &&
                                (viewing.user.is_blocked ? (
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            router.delete(
                                                activityRoutes.users.unblock.url(
                                                    viewing.user!.id,
                                                ),
                                                {
                                                    preserveScroll: true,
                                                    onSuccess: () =>
                                                        setViewing(null),
                                                    onError,
                                                },
                                            )
                                        }
                                    >
                                        Unblock account
                                    </Button>
                                ) : (
                                    <Button
                                        variant="destructive"
                                        onClick={() => setBlocking(viewing)}
                                    >
                                        Block account
                                    </Button>
                                ))}
                            {viewing.ip_address && !viewing.ip_blocked && (
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        router.post(
                                            activityRoutes.ips.block.url(),
                                            {
                                                ip_address: viewing.ip_address,
                                                reason: `From activity #${viewing.id}`,
                                            },
                                            {
                                                preserveScroll: true,
                                                onSuccess: () =>
                                                    setViewing(null),
                                                onError,
                                            },
                                        )
                                    }
                                >
                                    Block IP
                                </Button>
                            )}
                        </>
                    )
                }
            />

            <DetailDialog
                open={blocking !== null}
                onOpenChange={(open) => !open && setBlocking(null)}
                title={`Block ${blocking?.user?.name ?? ''}?`}
                description="They are signed out now and cannot sign in until unblocked."
                rows={[]}
            >
                {blocking?.user && (
                    <Form
                        {...activityRoutes.users.block.form(blocking.user.id)}
                        options={{ preserveScroll: true }}
                        onSuccess={() => {
                            setBlocking(null);
                            setViewing(null);
                        }}
                        className="grid gap-3"
                    >
                        {({ errors, processing }) => (
                            <>
                                <Label htmlFor="block-reason">Reason</Label>
                                <Textarea
                                    id="block-reason"
                                    name="reason"
                                    rows={3}
                                    required
                                />
                                <InputError message={errors.reason} />
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    Block account
                                </Button>
                            </>
                        )}
                    </Form>
                )}
            </DetailDialog>
        </>
    );
}

ActivityIndex.layout = {
    breadcrumbs: [{ title: 'Activity log', href: activityRoutes.index() }],
};
