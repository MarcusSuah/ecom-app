import { useHttp } from '@inertiajs/react';
import { ExternalLink, Send } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { CounterpartCard, MessageList } from '@/components/chat/chat-thread';
import type { ChatPayload } from '@/components/chat/chat-thread';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import conversationRoutes from '@/routes/conversations';

const POLL_MS = 4000;

/**
 * Chat with a seller in a dialog over the current page: shows who the
 * seller is and the conversation (opened or reused about the product or
 * RFQ), and checks for replies while it is open.
 */
export function ChatDialog({
    open,
    onOpenChange,
    company,
    rfqId,
    productId,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    company: string;
    rfqId?: number;
    productId?: number;
}) {
    const [payload, setPayload] = useState<ChatPayload | null>(null);
    const [failed, setFailed] = useState<string | null>(null);
    const starter = useHttp<
        { company: string; rfq_id: number | null; product_id: number | null },
        ChatPayload
    >({ company, rfq_id: rfqId ?? null, product_id: productId ?? null });
    const composer = useHttp<{ body: string }, ChatPayload>({ body: '' });
    const conversationId = payload?.conversation.id;

    useEffect(() => {
        if (!open || payload) {
            return;
        }

        setFailed(null);
        void starter.post(conversationRoutes.open.url(), {
            onSuccess: (data) => setPayload(data),
            onHttpException: () =>
                setFailed('The chat could not be opened. Please try again.'),
            onNetworkError: () =>
                setFailed('You seem to be offline. Please try again.'),
        });
        // Opening once per dialog session; starter is stable enough for this.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const refresh = useCallback(async () => {
        if (!conversationId) {
            return;
        }

        try {
            const response = await fetch(
                conversationRoutes.feed.url(conversationId),
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                },
            );

            if (response.ok) {
                setPayload((await response.json()) as ChatPayload);
            }
        } catch {
            // The next poll tries again.
        }
    }, [conversationId]);

    useEffect(() => {
        if (!open || !conversationId) {
            return;
        }

        const timer = window.setInterval(() => {
            if (document.visibilityState === 'visible') {
                void refresh();
            }
        }, POLL_MS);

        return () => window.clearInterval(timer);
    }, [open, conversationId, refresh]);

    const send = (event: FormEvent | { preventDefault(): void }) => {
        event.preventDefault();

        if (
            !conversationId ||
            composer.processing ||
            !composer.data.body.trim()
        ) {
            return;
        }

        void composer.post(
            conversationRoutes.messages.store.url(conversationId),
            {
                onSuccess: (data) => {
                    setPayload(data);
                    composer.reset();
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[90vh] flex-col gap-3 sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {payload
                            ? `Chat with ${payload.conversation.with}`
                            : 'Chat'}
                    </DialogTitle>
                    <DialogDescription>
                        {payload?.conversation.subject ??
                            'Ask about prices, stock, delivery or samples.'}
                    </DialogDescription>
                </DialogHeader>

                {failed && (
                    <p role="alert" className="text-sm text-destructive">
                        {failed}
                    </p>
                )}

                {!payload && !failed && (
                    <div className="flex items-center justify-center gap-2 py-10 text-sm text-muted-foreground">
                        <Spinner /> Opening the chat…
                    </div>
                )}

                {payload && (
                    <>
                        <CounterpartCard counterpart={payload.counterpart} />
                        <MessageList
                            messages={payload.messages}
                            className="min-h-40 flex-1 border-y"
                        />
                        <form onSubmit={send} className="space-y-2">
                            <Label
                                htmlFor="chat-dialog-body"
                                className="sr-only"
                            >
                                Message
                            </Label>
                            <Textarea
                                id="chat-dialog-body"
                                rows={2}
                                value={composer.data.body}
                                onChange={(event) =>
                                    composer.setData('body', event.target.value)
                                }
                                onKeyDown={(event) => {
                                    if (
                                        event.key === 'Enter' &&
                                        !event.shiftKey
                                    ) {
                                        send(event);
                                    }
                                }}
                                placeholder="Write a message. Enter sends, Shift+Enter adds a line."
                                autoFocus
                            />
                            <InputError message={composer.errors.body} />
                            <div className="flex items-center justify-between gap-2">
                                <a
                                    href={payload.conversation.url}
                                    className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                                >
                                    <ExternalLink
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Open full chat (attachments)
                                </a>
                                <Button
                                    type="submit"
                                    disabled={
                                        composer.processing ||
                                        !composer.data.body.trim()
                                    }
                                >
                                    <Send aria-hidden="true" />
                                    Send
                                </Button>
                            </div>
                        </form>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
