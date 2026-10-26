import { Head, Link, router } from '@inertiajs/react';
import { BellOff, CheckCheck, ChevronRight } from 'lucide-react';
import Heading from '@/components/heading';
import { Pagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { notificationKind } from '@/lib/notification-kinds';
import { cn } from '@/lib/utils';
import notificationRoutes from '@/routes/notifications';
import type { Paginated } from '@/types';

type NotificationItem = {
    id: string;
    type: string;
    title: string;
    message: string;
    link_url: string | null;
    read_at: string | null;
    created_at: string | null;
};

/** "5 min ago", "Yesterday", "12 Oct" — the full date is in the tooltip. */
function when(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);
    const minutes = Math.round((Date.now() - date.getTime()) / 60000);

    if (minutes < 1) {
        return 'Just now';
    }

    if (minutes < 60) {
        return `${minutes} min ago`;
    }

    if (minutes < 24 * 60) {
        return `${Math.round(minutes / 60)} h ago`;
    }

    if (minutes < 48 * 60) {
        return 'Yesterday';
    }

    return date.toLocaleDateString(undefined, {
        day: 'numeric',
        month: 'short',
        year:
            date.getFullYear() === new Date().getFullYear()
                ? undefined
                : 'numeric',
    });
}

/**
 * The member's notifications on this marketplace: what each one is about
 * (icon and kind), when it arrived, whether it was read, and a way to it.
 */
export default function Notifications({
    notifications,
    filter,
    counts,
}: {
    notifications: Paginated<NotificationItem>;
    filter: 'all' | 'unread';
    counts: { all: number; unread: number };
}) {
    const show = (next: 'all' | 'unread') =>
        router.get(
            notificationRoutes.index.url(),
            next === 'unread' ? { filter: 'unread' } : {},
            { preserveScroll: true, preserveState: true, replace: true },
        );

    return (
        <>
            <Head title="Notifications" />
            <div className="mx-auto w-full max-w-5xl space-y-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Notifications"
                        description="Orders, quotes, requests and messages from the marketplace."
                    />
                    {counts.unread > 0 && (
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.post(
                                    notificationRoutes.readAll.url(),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <CheckCheck aria-hidden="true" />
                            Mark all as read
                        </Button>
                    )}
                </div>

                <div
                    role="tablist"
                    aria-label="Show"
                    className="inline-flex rounded-md border p-0.5"
                >
                    {(['all', 'unread'] as const).map((option) => (
                        <button
                            key={option}
                            type="button"
                            role="tab"
                            aria-selected={filter === option}
                            onClick={() => show(option)}
                            className={cn(
                                'rounded px-3 py-1 text-sm text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                filter === option &&
                                    'bg-primary text-primary-foreground hover:text-primary-foreground',
                            )}
                        >
                            {option === 'all' ? 'All' : 'Unread'}{' '}
                            <span className="tabular-nums opacity-80">
                                ({counts[option]})
                            </span>
                        </button>
                    ))}
                </div>

                {notifications.data.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 rounded-md border p-10 text-center text-sm text-muted-foreground">
                        <BellOff className="size-8" aria-hidden="true" />
                        {filter === 'unread'
                            ? 'You have read everything.'
                            : 'You have no notifications yet.'}
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-14">
                                        <span className="sr-only">Kind</span>
                                    </TableHead>
                                    <TableHead>Notification</TableHead>
                                    <TableHead className="w-32">
                                        Received
                                    </TableHead>
                                    <TableHead className="w-24">
                                        Status
                                    </TableHead>
                                    <TableHead className="w-24 text-right">
                                        <span className="sr-only">Open</span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {notifications.data.map((notification) => {
                                    const kind = notificationKind(
                                        notification.type,
                                    );
                                    const Icon = kind.icon;
                                    const unread = !notification.read_at;

                                    return (
                                        <TableRow
                                            key={notification.id}
                                            className={cn(
                                                unread && 'bg-primary/5',
                                            )}
                                        >
                                            <TableCell>
                                                <span
                                                    className={cn(
                                                        'flex size-9 items-center justify-center rounded-lg',
                                                        kind.tone,
                                                    )}
                                                    title={kind.label}
                                                >
                                                    <Icon
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    <span className="sr-only">
                                                        {kind.label}
                                                    </span>
                                                </span>
                                            </TableCell>
                                            <TableCell className="max-w-xl whitespace-normal">
                                                <p
                                                    className={cn(
                                                        'text-sm',
                                                        unread
                                                            ? 'font-semibold'
                                                            : 'font-medium',
                                                    )}
                                                >
                                                    {notification.title}
                                                </p>
                                                <p className="mt-0.5 line-clamp-2 text-sm text-muted-foreground">
                                                    {notification.message}
                                                </p>
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                <time
                                                    dateTime={
                                                        notification.created_at ??
                                                        undefined
                                                    }
                                                    title={
                                                        notification.created_at
                                                            ? new Date(
                                                                  notification.created_at,
                                                              ).toLocaleString()
                                                            : undefined
                                                    }
                                                >
                                                    {when(
                                                        notification.created_at,
                                                    )}
                                                </time>
                                            </TableCell>
                                            <TableCell>
                                                {unread ? (
                                                    <span className="inline-flex items-center gap-1.5 text-xs font-medium text-primary">
                                                        <span
                                                            className="size-2 rounded-full bg-primary"
                                                            aria-hidden="true"
                                                        />
                                                        New
                                                    </span>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">
                                                        Read
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {notification.link_url && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        {/* Opening marks it read, then goes to what it is about. */}
                                                        <Link
                                                            href={notificationRoutes.open.url(
                                                                notification.id,
                                                            )}
                                                        >
                                                            Open
                                                            <ChevronRight aria-hidden="true" />
                                                        </Link>
                                                    </Button>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    </div>
                )}

                <Pagination page={notifications} />
            </div>
        </>
    );
}

Notifications.layout = {
    breadcrumbs: [{ title: 'Notifications', href: notificationRoutes.index() }],
};
