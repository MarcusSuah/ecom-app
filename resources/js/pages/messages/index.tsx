import { Head, Link, usePoll } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import conversationRoutes from '@/routes/conversations';
import type { Paginated } from '@/types';

type Row = {
    id: number;
    subject: string;
    with: string;
    unread_count: number;
    last_message_at: string | null;
};

export default function Conversations({
    conversations,
}: {
    conversations: Paginated<Row>;
}) {
    // No WebSocket server yet: refresh the list now and then.
    usePoll(15000, { only: ['conversations', 'unreadMessagesCount'] });

    return (
        <>
            <Head title="Messages" />
            <div className="mx-auto w-full max-w-3xl p-4">
                <Heading title="Messages" />
                {conversations.data.length === 0 ? (
                    <p className="rounded-md border p-8 text-center text-sm text-muted-foreground">
                        No conversations yet. Use "Open chat" on a product,
                        supplier or quote.
                    </p>
                ) : (
                    <ul className="divide-y rounded-md border">
                        {conversations.data.map((conversation) => (
                            <li key={conversation.id}>
                                <Link
                                    href={conversationRoutes.show(
                                        conversation.id,
                                    )}
                                    className={cn(
                                        'flex items-center justify-between gap-3 p-4 hover:bg-accent focus-visible:bg-accent',
                                        conversation.unread_count > 0 &&
                                            'font-medium',
                                    )}
                                >
                                    <span className="min-w-0">
                                        <span className="block truncate">
                                            {conversation.with}
                                        </span>
                                        <span className="block truncate text-sm font-normal text-muted-foreground">
                                            {conversation.subject}
                                        </span>
                                    </span>
                                    <span className="flex shrink-0 items-center gap-2 text-xs text-muted-foreground">
                                        {formatDate(
                                            conversation.last_message_at,
                                        )}
                                        {conversation.unread_count > 0 && (
                                            <Badge
                                                aria-label={`${conversation.unread_count} unread`}
                                            >
                                                {conversation.unread_count}
                                            </Badge>
                                        )}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
                <Pagination page={conversations} />
            </div>
        </>
    );
}

Conversations.layout = {
    breadcrumbs: [{ title: 'Messages', href: conversationRoutes.index() }],
};
