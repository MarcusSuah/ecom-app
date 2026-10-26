import { Head } from '@inertiajs/react';

const messages: Record<number, { title: string; text: string }> = {
    403: {
        title: 'You cannot open this page',
        text: 'Your account does not have access to it. If you think it should, contact the marketplace team.',
    },
    404: {
        title: 'Page not found',
        text: 'The page may have moved, or the link is mistyped. Products and shops that are no longer listed are hidden too.',
    },
    419: {
        title: 'This page expired',
        text: 'You were away for a while, so the form timed out for your security. Go back, refresh the page and try again.',
    },
    429: {
        title: 'Too many requests',
        text: 'Please wait a minute before trying again.',
    },
    500: {
        title: 'Something went wrong',
        text: 'We could not finish that request. Nothing was charged. Please try again in a moment.',
    },
    503: {
        title: 'Down for maintenance',
        text: 'We are making improvements and will be back shortly.',
    },
};

/**
 * Shown instead of the framework's error pages when debug output is off
 * (AppServiceProvider::configureErrorPages). Plain links, no shared data:
 * the error may have come from the database.
 */
export default function ErrorPage({
    status,
    marketplace,
}: {
    status: number;
    marketplace: string;
}) {
    const message = messages[status] ?? messages[500];

    return (
        <>
            <Head title={message.title} />
            <main className="flex min-h-screen items-center justify-center bg-background p-6 text-foreground">
                <div className="max-w-md space-y-4 text-center">
                    <p className="text-sm font-medium text-muted-foreground">
                        {marketplace} · Error {status}
                    </p>
                    <h1 className="text-2xl font-semibold">{message.title}</h1>
                    <p className="text-muted-foreground">{message.text}</p>
                    <div className="flex flex-wrap justify-center gap-3 pt-2 text-sm">
                        <a
                            href="/"
                            className="rounded-md bg-primary px-4 py-2 text-primary-foreground hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                        >
                            Go to the home page
                        </a>
                        {status !== 503 && (
                            <a
                                href="/contact"
                                className="rounded-md border px-4 py-2 hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                Contact us
                            </a>
                        )}
                    </div>
                </div>
            </main>
        </>
    );
}
