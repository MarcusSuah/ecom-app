import { Link, router, usePage } from '@inertiajs/react';
import { Bell, CheckCheck } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { notificationKind } from '@/lib/notification-kinds';
import { cn } from '@/lib/utils';
import {
    index as notificationsIndex,
    open as openNotification,
    readAll,
    recent,
} from '@/routes/notifications';
import type { SharedData } from '@/types';

type Item = {
    id: string;
    type: string;
    title: string;
    message: string;
    link_url: string | null;
    read_at: string | null;
    created_at: string | null;
};

function timeAgo(value: string | null): string {
    if (!value) {
        return '';
    }

    const minutes = Math.round(
        (Date.now() - new Date(value).getTime()) / 60000,
    );

    if (minutes < 1) {
        return 'just now';
    }

    if (minutes < 60) {
        return `${minutes} min ago`;
    }

    const hours = Math.round(minutes / 60);

    return hours < 24 ? `${hours} h ago` : new Date(value).toLocaleDateString();
}

/**
 * The header bell: the unread count from shared props, and the latest
 * notifications loaded when it is opened. Opening one marks it read.
 */
export function NotificationBell({ className }: { className?: string }) {
    const { auth, unreadNotificationsCount } = usePage<SharedData>().props;
    const [items, setItems] = useState<Item[] | null>(null);
    const [failed, setFailed] = useState(false);

    if (!auth.user) {
        return null;
    }

    const load = async (open: boolean) => {
        if (!open) {
            return;
        }

        try {
            const response = await fetch(recent.url(), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const data = (await response.json()) as { notifications: Item[] };
            setItems(data.notifications);
            setFailed(false);
        } catch {
            setFailed(true);
        }
    };

    const unread = unreadNotificationsCount ?? 0;

    return (
        <DropdownMenu onOpenChange={load}>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className={cn('relative', className)}
                    aria-label={
                        unread > 0
                            ? `Notifications, ${unread} unread`
                            : 'Notifications'
                    }
                >
                    <Bell className="size-5" />
                    {unread > 0 && (
                        <span
                            className="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] leading-none font-semibold text-white"
                            aria-hidden="true"
                        >
                            {unread > 99 ? '99+' : unread}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80 p-0">
                <div className="flex items-center justify-between border-b px-3 py-2">
                    <span className="text-sm font-semibold">Notifications</span>
                    {unread > 0 && (
                        <button
                            type="button"
                            className="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                            onClick={() =>
                                router.post(
                                    readAll.url(),
                                    {},
                                    {
                                        preserveScroll: true,
                                        onSuccess: () =>
                                            setItems(
                                                (current) =>
                                                    current?.map((item) => ({
                                                        ...item,
                                                        read_at:
                                                            item.read_at ??
                                                            new Date().toISOString(),
                                                    })) ?? null,
                                            ),
                                    },
                                )
                            }
                        >
                            <CheckCheck
                                className="size-3.5"
                                aria-hidden="true"
                            />
                            Mark all read
                        </button>
                    )}
                </div>
                <div className="max-h-96 overflow-y-auto">
                    {items === null && !failed && (
                        <p className="p-4 text-center text-sm text-muted-foreground">
                            Loading…
                        </p>
                    )}
                    {failed && (
                        <p className="p-4 text-center text-sm text-muted-foreground">
                            Could not load notifications.
                        </p>
                    )}
                    {items?.length === 0 && (
                        <p className="p-4 text-center text-sm text-muted-foreground">
                            You're all caught up.
                        </p>
                    )}
                    {items && items.length > 0 && (
                        <ul className="divide-y">
                            {items.map((item) => (
                                <li key={item.id}>
                                    <Link
                                        href={openNotification.url(item.id)}
                                        className={cn(
                                            'flex gap-2 px-3 py-2.5 text-sm hover:bg-accent focus-visible:bg-accent focus-visible:outline-none',
                                            !item.read_at && 'bg-primary/5',
                                        )}
                                    >
                                        <BellItemIcon
                                            type={item.type}
                                            unread={!item.read_at}
                                        />
                                        <span className="min-w-0">
                                            <span className="block font-medium">
                                                {item.title}
                                                {!item.read_at && (
                                                    <span className="sr-only">
                                                        {' '}
                                                        (unread)
                                                    </span>
                                                )}
                                            </span>
                                            <span className="line-clamp-2 text-muted-foreground">
                                                {item.message}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {timeAgo(item.created_at)}
                                            </span>
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
                <div className="border-t p-2">
                    <Link
                        href={notificationsIndex()}
                        className="block rounded px-2 py-1.5 text-center text-sm font-medium text-primary hover:bg-accent"
                    >
                        View all notifications
                    </Link>
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/** The notification's kind as an icon, with a dot while unread. */
function BellItemIcon({ type, unread }: { type: string; unread: boolean }) {
    const kind = notificationKind(type);
    const Icon = kind.icon;

    return (
        <span
            className={cn(
                'relative mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-md',
                kind.tone,
            )}
            aria-hidden="true"
        >
            <Icon className="size-3.5" />
            {unread && (
                <span className="absolute -top-0.5 -right-0.5 size-2 rounded-full bg-primary ring-2 ring-background" />
            )}
        </span>
    );
}
