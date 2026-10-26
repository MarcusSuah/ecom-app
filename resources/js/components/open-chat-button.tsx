import { router, usePage } from '@inertiajs/react';
import { MessageSquare } from 'lucide-react';
import { useState } from 'react';
import { ChatDialog } from '@/components/chat/chat-dialog';
import { Button } from '@/components/ui/button';
import { login } from '@/routes';
import type { SharedData } from '@/types';

/**
 * Opens the chat with a company in a dialog over the current page,
 * optionally about an RFQ or a product. Guests are sent to log in first.
 */
export function OpenChatButton({
    company,
    rfqId,
    productId,
    label = 'Open chat',
    variant = 'outline',
    className,
}: {
    company: string;
    rfqId?: number;
    productId?: number;
    label?: string;
    variant?: 'default' | 'outline' | 'secondary';
    className?: string;
}) {
    const { auth } = usePage<SharedData>().props;
    const [open, setOpen] = useState(false);

    const start = () => {
        if (!auth.user) {
            router.visit(login());

            return;
        }

        setOpen(true);
    };

    return (
        <>
            <Button
                type="button"
                variant={variant}
                onClick={start}
                className={className}
                aria-haspopup="dialog"
            >
                <MessageSquare aria-hidden="true" />
                {label}
            </Button>
            {auth.user && (
                <ChatDialog
                    open={open}
                    onOpenChange={setOpen}
                    company={company}
                    rfqId={rfqId}
                    productId={productId}
                />
            )}
        </>
    );
}
