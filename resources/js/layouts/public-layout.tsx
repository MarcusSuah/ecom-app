import { Link, router, usePage } from '@inertiajs/react';
import {
    ClipboardList,
    Facebook,
    Instagram,
    Linkedin,
    Mail,
    MapPin,
    Moon,
    Phone,
    ShoppingCart,
    Sun,
    Twitter,
} from 'lucide-react';
import { type ReactNode } from 'react';
import { BasketExpiry } from '@/components/basket-expiry';
import { NotificationBell } from '@/components/notification-bell';
import { Seo } from '@/components/seo';
import { HeaderSearch } from '@/components/storefront/header-search';
import {
    StorefrontPanelsProvider,
    useStorefrontPanels,
} from '@/components/storefront/storefront-panels';
import { useInquiryList } from '@/lib/inquiry-list';
import { useAppearance } from '@/hooks/use-appearance';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { dashboard, home, login, register } from '@/routes';
import { index as categoriesIndex } from '@/routes/categories';
import { create as contact } from '@/routes/contact';
import { update as updateCurrency } from '@/routes/currency';
import { show as contentPage } from '@/routes/pages';
import { index as productsIndex } from '@/routes/products';
import { create as requestQuote } from '@/routes/rfqs';
import { index as shopsIndex } from '@/routes/shops';
import { index as suppliersIndex } from '@/routes/suppliers';
import type { SharedData } from '@/types';

const links = [
    { title: 'Wholesale products', href: productsIndex() },
    { title: 'Local shops', href: shopsIndex() },
    { title: 'Categories', href: categoriesIndex() },
    { title: 'Suppliers', href: suppliersIndex() },
    { title: 'Request quotes', href: requestQuote() },
];

/**
 * The marketplace's logo from Settings, or the template's mark: two
 * overlapping circles in the accent and highlight colours.
 */
export function BrandMark({ className }: { className?: string }) {
    const { marketplace } = usePage<SharedData>().props;

    if (marketplace?.logo_url) {
        return (
            <img
                src={marketplace.logo_url}
                alt=""
                className={cn(
                    'h-[38px] w-auto max-w-[120px] shrink-0 object-contain',
                    className,
                )}
            />
        );
    }

    return (
        <svg
            viewBox="0 0 40 40"
            aria-hidden="true"
            className={cn('size-[38px] shrink-0', className)}
        >
            <circle cx="15" cy="20" r="13" fill="var(--sf-accent)" />
            <circle
                cx="26"
                cy="20"
                r="13"
                fill="var(--sf-mark)"
                style={{ mixBlendMode: 'multiply' }}
            />
        </svg>
    );
}

/** "Mix Ventures" with a small "Marketplace" underneath, like the template. */
export function BrandName({ name }: { name: string }) {
    const main = name.replace(/\s+marketplace$/i, '') || name;

    return (
        <span className="min-w-0">
            <b className="sf-display block truncate text-xl leading-none font-extrabold tracking-tight">
                {main}
            </b>
            <small className="sf-mono mt-0.5 block text-[10.5px] tracking-[0.14em] text-[var(--sf-muted)] uppercase">
                Marketplace
            </small>
        </span>
    );
}

/**
 * Header and footer for the public pages, in the storefront style and
 * branded with the current marketplace.
 */
export default function PublicLayout({ children }: { children: ReactNode }) {
    return (
        <StorefrontPanelsProvider>
            <StorefrontShell>{children}</StorefrontShell>
        </StorefrontPanelsProvider>
    );
}

/** The two header buttons that open the inquiry list and local order drawers. */
function BasketButtons({ cartCount }: { cartCount: number }) {
    const { openInquiryList, openLocalOrder } = useStorefrontPanels();
    const inquiryCount = useInquiryList().length;

    return (
        <>
            <button
                type="button"
                onClick={openInquiryList}
                className="sf-btn"
                aria-haspopup="dialog"
                aria-label={`Inquiry list, ${inquiryCount} products`}
            >
                <ClipboardList className="size-4" aria-hidden="true" />
                <span className="hidden sm:inline">Inquiry list</span>
                <span className="sf-badge">{inquiryCount}</span>
            </button>
            <button
                type="button"
                onClick={openLocalOrder}
                className="sf-btn sf-btn-primary"
                aria-haspopup="dialog"
                aria-label={`Local order, ${cartCount} items`}
            >
                <ShoppingCart className="size-4" aria-hidden="true" />
                <span className="hidden sm:inline">Local order</span>
                <span className="sf-badge">{cartCount}</span>
            </button>
        </>
    );
}

function StorefrontShell({ children }: { children: ReactNode }) {
    const {
        auth,
        tenant,
        name,
        currency,
        paymentProtectionLabel,
        cartCount,
        marketplace,
    } = usePage<SharedData>().props;
    const { isCurrentUrl } = useCurrentUrl();
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const siteName = tenant?.name ?? name;
    const footer = [
        {
            heading: 'Buy',
            links: [
                { title: 'Browse products', href: productsIndex() },
                { title: 'Request quotes', href: requestQuote() },
                { title: 'Find suppliers', href: suppliersIndex() },
                { title: 'Local shops', href: shopsIndex() },
            ],
        },
        {
            heading: 'Sell',
            links: [
                { title: 'List your products', href: contentPage('sell') },
                { title: 'Get verified', href: contentPage('get-verified') },
                { title: 'Seller guide', href: contentPage('seller-guide') },
            ],
        },
        {
            heading: 'Support',
            links: [
                {
                    title: paymentProtectionLabel,
                    href: contentPage('payment-protection'),
                },
                {
                    title: 'Shipping and customs',
                    href: contentPage('shipping-and-customs'),
                },
                { title: 'Contact us', href: contact() },
            ],
        },
    ];

    const socials = [
        { href: marketplace?.facebook_url, label: 'Facebook', icon: Facebook },
        {
            href: marketplace?.instagram_url,
            label: 'Instagram',
            icon: Instagram,
        },
        { href: marketplace?.x_url, label: 'X', icon: Twitter },
        { href: marketplace?.linkedin_url, label: 'LinkedIn', icon: Linkedin },
    ].filter(
        (
            social,
        ): social is { href: string; label: string; icon: typeof Facebook } =>
            Boolean(social.href),
    );

    return (
        <div className="storefront flex min-h-screen flex-col">
            <Seo />
            <BasketExpiry />
            <a
                href="#main"
                className="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded-md focus:bg-[var(--sf-surface)] focus:px-3 focus:py-2"
            >
                Skip to content
            </a>

            <div className="sf-util">
                <div className="mx-auto flex max-w-[1240px] flex-wrap items-center justify-between gap-4 px-4 py-2">
                    <span className="opacity-85">{marketplace?.tagline}</span>
                    <div className="flex items-center gap-3">
                        {currency && currency.options.length > 1 && (
                            <>
                                <label htmlFor="sf-currency">Currency</label>
                                <select
                                    id="sf-currency"
                                    value={currency.display}
                                    onChange={(event) =>
                                        router.put(
                                            updateCurrency.url(),
                                            { currency: event.target.value },
                                            {
                                                preserveScroll: true,
                                                preserveState: true,
                                            },
                                        )
                                    }
                                >
                                    {currency.options.map((option) => (
                                        <option key={option} value={option}>
                                            {option}
                                        </option>
                                    ))}
                                </select>
                            </>
                        )}
                        <button
                            type="button"
                            onClick={() =>
                                updateAppearance(
                                    resolvedAppearance === 'dark'
                                        ? 'light'
                                        : 'dark',
                                )
                            }
                            aria-label={`Use ${resolvedAppearance === 'dark' ? 'light' : 'dark'} theme`}
                        >
                            <span className="inline-flex items-center gap-1">
                                {resolvedAppearance === 'dark' ? (
                                    <Sun
                                        className="size-3.5"
                                        aria-hidden="true"
                                    />
                                ) : (
                                    <Moon
                                        className="size-3.5"
                                        aria-hidden="true"
                                    />
                                )}
                                Theme
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            <header className="sticky top-0 z-20 border-b border-[var(--sf-line)] bg-[var(--sf-surface)]">
                <div className="mx-auto grid max-w-[1240px] grid-cols-[1fr_auto] items-center gap-4 px-4 py-3 md:grid-cols-[auto_minmax(0,1fr)_auto]">
                    <Link
                        href={home()}
                        className="flex min-w-0 items-center gap-2.5 rounded-md"
                        aria-label={`${siteName} home`}
                    >
                        <BrandMark />
                        <BrandName name={siteName} />
                    </Link>
                    <HeaderSearch className="relative order-3 col-span-2 md:order-none md:col-span-1" />
                    <div className="flex items-center gap-2">
                        <Link
                            href={requestQuote()}
                            className="sf-btn hidden lg:inline-flex"
                        >
                            Request quotes
                        </Link>
                        <BasketButtons cartCount={cartCount ?? 0} />
                        {auth.user ? (
                            <>
                                <NotificationBell />
                                <Link href={dashboard()} className="sf-btn">
                                    Dashboard
                                </Link>
                            </>
                        ) : (
                            <>
                                <Link
                                    href={login()}
                                    className="sf-btn hidden sm:inline-flex"
                                >
                                    Log in
                                </Link>
                                <Link
                                    href={register()}
                                    className="sf-btn hidden md:inline-flex"
                                >
                                    Register
                                </Link>
                            </>
                        )}
                    </div>
                </div>
                <nav
                    aria-label="Storefront"
                    className="border-t border-[var(--sf-line)]"
                >
                    <div className="mx-auto flex max-w-[1240px] gap-5 overflow-x-auto px-4 py-2 text-sm">
                        {links.map((link) => (
                            <Link
                                key={link.title}
                                href={link.href}
                                className={cn(
                                    'whitespace-nowrap text-[var(--sf-muted)] hover:text-[var(--sf-ink)]',
                                    isCurrentUrl(link.href) &&
                                        'font-semibold text-[var(--sf-ink)]',
                                )}
                                aria-current={
                                    isCurrentUrl(link.href) ? 'page' : undefined
                                }
                            >
                                {link.title}
                            </Link>
                        ))}
                    </div>
                </nav>
            </header>

            <main
                id="main"
                className="mx-auto w-full max-w-[1240px] flex-1 px-4 py-6"
            >
                {children}
            </main>

            <footer className="mt-14 border-t border-[var(--sf-line)] bg-[var(--sf-surface)] pt-9 pb-7">
                <div className="mx-auto max-w-[1240px] px-4">
                    <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_1fr]">
                        <div className="space-y-3">
                            <Link
                                href={home()}
                                className="flex items-center gap-2.5"
                                aria-label={`${siteName} home`}
                            >
                                <BrandMark />
                                <BrandName name={siteName} />
                            </Link>
                            <p className="max-w-[34ch] text-[var(--sf-muted)]">
                                {marketplace?.about ?? marketplace?.tagline}
                            </p>
                            <ul className="space-y-1 text-sm text-[var(--sf-muted)]">
                                {marketplace?.support_email && (
                                    <li className="flex items-center gap-2">
                                        <Mail
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        <a
                                            href={`mailto:${marketplace.support_email}`}
                                            className="hover:underline"
                                        >
                                            {marketplace.support_email}
                                        </a>
                                    </li>
                                )}
                                {marketplace?.support_phone && (
                                    <li className="flex items-center gap-2">
                                        <Phone
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        <a
                                            href={`tel:${marketplace.support_phone.replaceAll(' ', '')}`}
                                            className="hover:underline"
                                        >
                                            {marketplace.support_phone}
                                        </a>
                                    </li>
                                )}
                                {marketplace?.address && (
                                    <li className="flex items-center gap-2">
                                        <MapPin
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {marketplace.address}
                                    </li>
                                )}
                            </ul>
                            {socials.length > 0 && (
                                <ul className="flex gap-2">
                                    {socials.map((social) => (
                                        <li key={social.label}>
                                            <a
                                                href={social.href}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="sf-btn sf-btn-sm"
                                                aria-label={social.label}
                                            >
                                                <social.icon
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                            </a>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                        {footer.map((column) => (
                            <nav
                                key={column.heading}
                                aria-label={column.heading}
                            >
                                <h2 className="sf-eyebrow mb-2.5 !text-xs font-medium">
                                    {column.heading}
                                </h2>
                                <ul className="grid gap-1.5">
                                    {column.links.map((link) => (
                                        <li key={link.title}>
                                            <Link
                                                href={link.href}
                                                className="hover:underline"
                                            >
                                                {link.title}
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </nav>
                        ))}
                    </div>
                    <div className="mt-7 flex flex-wrap justify-between gap-3 border-t border-[var(--sf-line)] pt-4 text-sm text-[var(--sf-muted)]">
                        <span>
                            © {new Date().getFullYear()} {siteName}
                        </span>
                        {currency?.rate_label && (
                            <span className="sf-mono">
                                <span className="sr-only">
                                    Exchange rate used for approximate
                                    prices:{' '}
                                </span>
                                Rate: {currency.rate_label}
                            </span>
                        )}
                    </div>
                </div>
            </footer>
        </div>
    );
}
