import { BadgeCheck, FileText, Mail, MapPin, Phone } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { cn } from '@/lib/utils';

export type ChatMessage = {
    id: number;
    body: string | null;
    mine: boolean;
    sender: string;
    attachment: { name: string; mime: string; url: string } | null;
    read_at: string | null;
    created_at: string | null;
};

export type ChatCounterpart =
    | {
          kind: 'member';
          name: string;
          email: string | null;
          phone: string | null;
      }
    | {
          kind: 'supplier' | 'shop';
          name: string;
          slug: string;
          type: string;
          location: string | null;
          verified: boolean;
          phone: string | null;
          email: string | null;
          logo_url: string | null;
          profile_url: string;
      };

export type ChatPayload = {
    conversation: {
        id: number;
        subject: string;
        with: string;
        rfq: { id: number; title: string } | null;
        product: { name: string; slug: string } | null;
        url: string;
    };
    counterpart: ChatCounterpart;
    messages: ChatMessage[];
};

/**
 * Who the chat is with: the seller's name, type, place, verification and
 * contact details for a buyer; the member's contact details for a seller.
 */
export function CounterpartCard({
    counterpart,
}: {
    counterpart: ChatCounterpart;
}) {
    const initials = counterpart.name
        .split(/\s+/)
        .slice(0, 2)
        .map((word) => word[0]?.toUpperCase())
        .join('');

    return (
        <div className="flex items-start gap-3 rounded-lg border bg-muted/30 p-3">
            {counterpart.kind !== 'member' && counterpart.logo_url ? (
                <img
                    src={counterpart.logo_url}
                    alt=""
                    className="size-12 shrink-0 rounded-md object-cover"
                />
            ) : (
                <span
                    aria-hidden="true"
                    className="flex size-12 shrink-0 items-center justify-center rounded-md bg-primary/10 font-semibold text-primary"
                >
                    {initials}
                </span>
            )}
            <div className="min-w-0 space-y-1 text-sm">
                <p className="flex flex-wrap items-center gap-2 font-semibold">
                    {counterpart.kind === 'member' ? (
                        counterpart.name
                    ) : (
                        <a
                            href={counterpart.profile_url}
                            className="hover:underline"
                        >
                            {counterpart.name}
                        </a>
                    )}
                    {counterpart.kind !== 'member' && counterpart.verified && (
                        <span className="inline-flex items-center gap-1 text-xs font-medium text-emerald-700 dark:text-emerald-400">
                            <BadgeCheck
                                className="size-3.5"
                                aria-hidden="true"
                            />
                            Verified
                        </span>
                    )}
                </p>
                {counterpart.kind !== 'member' && (
                    <p className="flex flex-wrap gap-x-3 text-muted-foreground">
                        <span>{counterpart.type}</span>
                        {counterpart.location && (
                            <span className="inline-flex items-center gap-1">
                                <MapPin
                                    className="size-3.5"
                                    aria-hidden="true"
                                />
                                {counterpart.location}
                            </span>
                        )}
                    </p>
                )}
                <p className="flex flex-wrap gap-x-3 text-muted-foreground">
                    {counterpart.phone && (
                        <a
                            href={`tel:${counterpart.phone}`}
                            className="inline-flex items-center gap-1 hover:text-foreground"
                        >
                            <Phone className="size-3.5" aria-hidden="true" />
                            {counterpart.phone}
                        </a>
                    )}
                    {counterpart.email && (
                        <a
                            href={`mailto:${counterpart.email}`}
                            className="inline-flex items-center gap-1 break-all hover:text-foreground"
                        >
                            <Mail className="size-3.5" aria-hidden="true" />
                            {counterpart.email}
                        </a>
                    )}
                </p>
            </div>
        </div>
    );
}

/** The messages, newest at the bottom, scrolled into view as they arrive. */
export function MessageList({
    messages,
    className,
}: {
    messages: ChatMessage[];
    className?: string;
}) {
    const endRef = useRef<HTMLLIElement>(null);
    const lastId = messages.at(-1)?.id;

    useEffect(() => {
        endRef.current?.scrollIntoView({ block: 'end' });
    }, [lastId]);

    return (
        <ol
            className={cn('space-y-3 overflow-y-auto py-4', className)}
            aria-label="Messages"
            aria-live="polite"
        >
            {messages.length === 0 && (
                <li className="text-center text-sm text-muted-foreground">
                    No messages yet. Say hello and ask your question.
                </li>
            )}
            {messages.map((message) => (
                <li
                    key={message.id}
                    className={cn(
                        'flex',
                        message.mine ? 'justify-end' : 'justify-start',
                    )}
                >
                    <div
                        className={cn(
                            'max-w-[80%] rounded-lg px-3 py-2 text-sm',
                            message.mine
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted',
                        )}
                    >
                        <span className="sr-only">
                            {message.mine ? 'You' : message.sender}:{' '}
                        </span>
                        {message.body && (
                            <p className="whitespace-pre-line">
                                {message.body}
                            </p>
                        )}
                        {message.attachment &&
                            (message.attachment.mime.startsWith('image/') ? (
                                <a
                                    href={message.attachment.url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="mt-1 block"
                                >
                                    <img
                                        src={message.attachment.url}
                                        alt={message.attachment.name}
                                        className="max-h-48 rounded"
                                    />
                                </a>
                            ) : (
                                <a
                                    href={message.attachment.url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="mt-1 inline-flex items-center gap-1 underline"
                                >
                                    <FileText
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    {message.attachment.name}
                                </a>
                            ))}
                        <p
                            className={cn(
                                'mt-1 text-xs',
                                message.mine
                                    ? 'text-primary-foreground/70'
                                    : 'text-muted-foreground',
                            )}
                        >
                            {message.created_at
                                ? new Date(message.created_at).toLocaleString(
                                      undefined,
                                      {
                                          dateStyle: 'short',
                                          timeStyle: 'short',
                                      },
                                  )
                                : ''}
                            {message.mine && message.read_at ? ' · Read' : ''}
                        </p>
                    </div>
                </li>
            ))}
            <li ref={endRef} aria-hidden="true" />
        </ol>
    );
}
