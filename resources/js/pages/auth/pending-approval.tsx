import { Head, Link } from '@inertiajs/react';
import { Clock, ShieldX } from 'lucide-react';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { home, logout } from '@/routes';

export default function PendingApproval({
    status,
    reason,
    email,
}: {
    status: 'pending' | 'rejected';
    reason: string | null;
    email: string;
}) {
    const rejected = status === 'rejected';
    const Icon = rejected ? ShieldX : Clock;

    return (
        <>
            <Head
                title={
                    rejected ? 'Account not approved' : 'Waiting for approval'
                }
            />

            <div
                role="status"
                className="space-y-3 rounded-lg border bg-muted/40 p-4 text-sm"
            >
                <p className="flex items-center gap-2 font-medium">
                    <Icon className="size-4 text-primary" aria-hidden="true" />
                    {rejected
                        ? 'Your account was not approved.'
                        : 'Your account is waiting for approval.'}
                </p>
                {rejected ? (
                    reason && (
                        <p className="text-muted-foreground">
                            Reason: {reason}
                        </p>
                    )
                ) : (
                    <p className="text-muted-foreground">
                        Thanks for verifying your email. A marketplace
                        administrator reviews new accounts, usually within one
                        working day. We will email {email} as soon as you can
                        order, send requests and use your dashboard.
                    </p>
                )}
            </div>

            <div className="mt-6 flex flex-col items-center gap-3">
                <Button variant="outline" asChild>
                    <Link href={logout()} method="post" as="button">
                        Log out and browse as a guest
                    </Link>
                </Button>
                <TextLink href={home()} className="text-sm">
                    Check again
                </TextLink>
            </div>
        </>
    );
}

PendingApproval.layout = {
    title: 'Account review',
    description:
        'New accounts are checked by the marketplace team before they can be used.',
};
