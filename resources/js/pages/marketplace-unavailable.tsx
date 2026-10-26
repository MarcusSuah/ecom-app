import { Head, Link, usePage } from '@inertiajs/react';
import { login } from '@/routes';
import type { SharedData } from '@/types';

export default function MarketplaceUnavailable({
    marketplace,
    reason,
}: {
    marketplace: string;
    reason: 'suspended' | 'billing';
}) {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="Marketplace unavailable" />
            <main className="flex min-h-screen items-center justify-center bg-background p-6">
                <div className="max-w-md space-y-4 text-center">
                    <h1 className="text-2xl font-semibold">
                        {marketplace} is not available right now
                    </h1>
                    <p className="text-muted-foreground">
                        {reason === 'suspended'
                            ? 'This marketplace has been paused. Please check back later.'
                            : 'This marketplace is temporarily closed. Please check back soon.'}
                    </p>
                    {!auth.user && (
                        <p className="text-sm">
                            Run this marketplace?{' '}
                            <Link
                                href={login()}
                                className="text-primary underline-offset-4 hover:underline"
                            >
                                Sign in
                            </Link>{' '}
                            to sort out its plan.
                        </p>
                    )}
                </div>
            </main>
        </>
    );
}
