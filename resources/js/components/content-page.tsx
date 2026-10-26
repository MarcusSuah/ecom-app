import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { create as contact } from '@/routes/contact';
import { show as contentPage } from '@/routes/pages';

/** Props every footer page receives from ContentPageController. */
export type ContentFacts = {
    autoReleaseDays: number;
    defaultCommissionPercent: number;
    protectionLabel: string;
    roadFactor: number;
    maxDocuments: number;
    rfqOpenDays: number;
};

export type ContentPageProps = {
    title: string;
    facts: ContentFacts;
    disputeReasons: string[];
};

const related = [
    { slug: 'sell', title: 'List your products' },
    { slug: 'get-verified', title: 'Get verified' },
    { slug: 'seller-guide', title: 'Seller guide' },
    { slug: 'payment-protection', title: null },
    { slug: 'shipping-and-customs', title: 'Shipping and customs' },
];

/**
 * Layout for the information pages: title, lead, readable body and links
 * to the other pages.
 */
export function ContentPage({
    title,
    lead,
    current,
    protectionLabel,
    children,
}: {
    title: string;
    lead: string;
    current: string;
    protectionLabel: string;
    children: ReactNode;
}) {
    return (
        <>
            <Head title={title} />
            <div className="grid gap-10 lg:grid-cols-[1fr_16rem]">
                <article className="max-w-3xl space-y-8">
                    <header className="space-y-2">
                        <h1 className="text-3xl font-semibold tracking-tight">
                            {title}
                        </h1>
                        <p className="text-lg text-muted-foreground">{lead}</p>
                    </header>
                    <div className="space-y-8 leading-relaxed [&_h2]:mb-2 [&_h2]:text-xl [&_h2]:font-semibold [&_li]:ml-5 [&_ol]:list-decimal [&_ol]:space-y-1 [&_p+p]:mt-3 [&_ul]:list-disc [&_ul]:space-y-1">
                        {children}
                    </div>
                </article>
                <aside
                    aria-label="More help"
                    className="space-y-3 text-sm lg:border-l lg:pl-6"
                >
                    <h2 className="font-semibold">More help</h2>
                    <ul className="space-y-1.5">
                        {related.map((page) => (
                            <li key={page.slug}>
                                <Link
                                    href={contentPage(page.slug)}
                                    className={
                                        page.slug === current
                                            ? 'font-medium text-foreground'
                                            : 'text-muted-foreground hover:text-foreground hover:underline'
                                    }
                                    aria-current={
                                        page.slug === current
                                            ? 'page'
                                            : undefined
                                    }
                                >
                                    {page.title ?? protectionLabel}
                                </Link>
                            </li>
                        ))}
                        <li>
                            <Link
                                href={contact()}
                                className="text-muted-foreground hover:text-foreground hover:underline"
                            >
                                Contact us
                            </Link>
                        </li>
                    </ul>
                </aside>
            </div>
        </>
    );
}
