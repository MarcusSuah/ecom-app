import { Head, Link, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import messageRoutes from '@/routes/admin/contact-messages';
import type { Paginated } from '@/types';

type Message = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    topic: string;
    message: string;
    is_member: boolean;
    created_at: string | null;
    handled_at: string | null;
    handled_by: string | null;
};

const statuses = ['open', 'handled'] as const;

export default function ContactMessagesIndex({
    messages,
    filters,
}: {
    messages: Paginated<Message>;
    filters: { status: (typeof statuses)[number] };
}) {
    return (
        <>
            <Head title="Contact messages" />
            <div className="max-w-4xl p-4">
                <Heading
                    title="Contact messages"
                    description="Sent from the Contact us page. Reply by email, then mark the message handled."
                />
                <nav aria-label="Filter by status" className="mb-4 flex gap-2">
                    {statuses.map((status) => (
                        <Link
                            key={status}
                            href={messageRoutes.index({ query: { status } })}
                            className={cn(
                                'rounded-md border px-3 py-1.5 text-sm capitalize hover:bg-accent',
                                filters.status === status &&
                                    'border-primary bg-primary text-primary-foreground hover:bg-primary',
                            )}
                            aria-current={
                                filters.status === status ? 'page' : undefined
                            }
                        >
                            {status}
                        </Link>
                    ))}
                </nav>
                {messages.data.length === 0 ? (
                    <p className="rounded-md border p-8 text-center text-sm text-muted-foreground">
                        No messages.
                    </p>
                ) : (
                    <ul className="space-y-3">
                        {messages.data.map((message) => (
                            <li
                                key={message.id}
                                className="space-y-2 rounded-lg border p-4"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <p className="font-medium">
                                            {message.name}{' '}
                                            {message.is_member && (
                                                <Badge variant="secondary">
                                                    Member
                                                </Badge>
                                            )}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            <a
                                                href={`mailto:${message.email}`}
                                                className="text-primary hover:underline"
                                            >
                                                {message.email}
                                            </a>
                                            {message.phone &&
                                                ` · ${message.phone}`}{' '}
                                            · {formatDate(message.created_at)}
                                        </p>
                                    </div>
                                    <Badge variant="outline">
                                        {message.topic}
                                    </Badge>
                                </div>
                                <p className="text-sm whitespace-pre-line">
                                    {message.message}
                                </p>
                                {message.handled_at ? (
                                    <p className="text-xs text-muted-foreground">
                                        Handled {formatDate(message.handled_at)}
                                        {message.handled_by &&
                                            ` by ${message.handled_by}`}
                                    </p>
                                ) : (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            router.post(
                                                messageRoutes.handled.url(
                                                    message.id,
                                                ),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        Mark handled
                                    </Button>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
                <Pagination page={messages} />
            </div>
        </>
    );
}

ContactMessagesIndex.layout = {
    breadcrumbs: [{ title: 'Contact messages', href: messageRoutes.index() }],
};
