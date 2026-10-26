import { Head, Link, useForm, usePoll } from '@inertiajs/react';
import { Paperclip, Send } from 'lucide-react';
import { useRef } from 'react';
import { CounterpartCard, MessageList } from '@/components/chat/chat-thread';
import type { ChatPayload } from '@/components/chat/chat-thread';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import conversationRoutes from '@/routes/conversations';
import { show as productShow } from '@/routes/products';
import { show as rfqShow } from '@/routes/rfqs';

type Props = ChatPayload;

export default function ConversationShow({
    conversation,
    counterpart,
    messages,
}: Props) {
    // Polling stands in for live delivery until a WebSocket server is added.
    usePoll(5000, { only: ['messages', 'unreadMessagesCount'] });

    const fileRef = useRef<HTMLInputElement>(null);
    const form = useForm<{ body: string; attachment: File | null }>({
        body: '',
        attachment: null,
    });
    const submit = (event: { preventDefault(): void }) => {
        event.preventDefault();
        form.post(conversationRoutes.messages.store.url(conversation.id), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                form.reset();
                if (fileRef.current) {
                    fileRef.current.value = '';
                }
            },
        });
    };

    return (
        <>
            <Head title={`Chat with ${conversation.with}`} />
            <div className="mx-auto flex h-[calc(100dvh-10rem)] min-h-[28rem] w-full max-w-3xl flex-col gap-3 p-4">
                <header className="border-b pb-3">
                    <h1 className="font-semibold">{conversation.with}</h1>
                    <p className="text-sm text-muted-foreground">
                        {conversation.rfq ? (
                            <Link
                                href={rfqShow(conversation.rfq.id)}
                                className="hover:underline"
                            >
                                {conversation.subject}
                            </Link>
                        ) : conversation.product ? (
                            <Link
                                href={productShow(conversation.product.slug)}
                                className="hover:underline"
                            >
                                {conversation.subject}
                            </Link>
                        ) : (
                            conversation.subject
                        )}
                    </p>
                </header>

                <CounterpartCard counterpart={counterpart} />

                <MessageList messages={messages} className="min-h-0 flex-1" />

                <form onSubmit={submit} className="space-y-2 border-t pt-3">
                    <Label htmlFor="body" className="sr-only">
                        Message
                    </Label>
                    <Textarea
                        id="body"
                        rows={2}
                        value={form.data.body}
                        onChange={(event) =>
                            form.setData('body', event.target.value)
                        }
                        onKeyDown={(event) => {
                            if (event.key === 'Enter' && !event.shiftKey) {
                                submit(event);
                            }
                        }}
                        placeholder="Write a message. Enter sends, Shift+Enter adds a line."
                    />
                    <InputError
                        message={form.errors.body ?? form.errors.attachment}
                    />
                    <div className="flex items-center justify-between gap-2">
                        <label className="inline-flex cursor-pointer items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                            <Paperclip className="size-4" aria-hidden="true" />
                            {form.data.attachment
                                ? form.data.attachment.name
                                : 'Attach image or PDF'}
                            <input
                                ref={fileRef}
                                type="file"
                                accept="image/jpeg,image/png,image/webp,application/pdf"
                                className="sr-only"
                                onChange={(event) =>
                                    form.setData(
                                        'attachment',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                            />
                        </label>
                        <Button type="submit" disabled={form.processing}>
                            <Send aria-hidden="true" />
                            Send
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

ConversationShow.layout = {
    breadcrumbs: [{ title: 'Messages', href: conversationRoutes.index() }],
};
