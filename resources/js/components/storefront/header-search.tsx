import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useId, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { useStorefrontPanels } from '@/components/storefront/storefront-panels';
import { LIVE_SEARCH_DELAY_MS } from '@/hooks/use-live-search';
import { search as searchRoute } from '@/routes';
import { index as productsIndex } from '@/routes/products';
import { suggest } from '@/routes/search';
import { show as shopShow } from '@/routes/shops';
import { show as supplierShow } from '@/routes/suppliers';
import type { SharedData } from '@/types';
import type { SearchResults } from '@/types/search';

const MIN_LENGTH = 2;

/**
 * The header search: suggestions from products, shop items, shops,
 * suppliers and categories appear while typing; Enter opens all results
 * (or the product list when a category is chosen).
 */
export function HeaderSearch({ className }: { className?: string }) {
    const { storefrontCategories } = usePage<SharedData>().props;
    const { openProduct } = useStorefrontPanels();
    const params =
        typeof window !== 'undefined'
            ? new URLSearchParams(window.location.search)
            : null;
    const [query, setQuery] = useState(params?.get('q') ?? '');
    const [category, setCategory] = useState(params?.get('category') ?? '');
    const [results, setResults] = useState<SearchResults | null>(null);
    const [open, setOpen] = useState(false);
    const listId = useId();
    const box = useRef<HTMLDivElement>(null);
    const request = useRef<AbortController | null>(null);

    useEffect(() => {
        const text = query.trim();

        if (text.length < MIN_LENGTH) {
            setResults(null);

            return;
        }

        const timer = window.setTimeout(async () => {
            request.current?.abort();
            const controller = new AbortController();
            request.current = controller;

            try {
                const response = await fetch(
                    suggest.url({ query: { q: text } }),
                    {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        signal: controller.signal,
                    },
                );

                if (response.ok) {
                    setResults((await response.json()) as SearchResults);
                    setOpen(true);
                }
            } catch {
                // Aborted by the next keystroke, or offline: keep typing.
            }
        }, LIVE_SEARCH_DELAY_MS);

        return () => window.clearTimeout(timer);
    }, [query]);

    useEffect(() => {
        const close = (event: MouseEvent) => {
            if (!box.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        };
        document.addEventListener('mousedown', close);

        return () => document.removeEventListener('mousedown', close);
    }, []);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        setOpen(false);
        const text = query.trim();

        if (category) {
            router.get(productsIndex.url(), {
                category,
                ...(text ? { q: text } : {}),
            });

            return;
        }

        router.get(searchRoute.url(), text ? { q: text } : {});
    };

    const showDropdown = open && results !== null;

    return (
        <div
            ref={box}
            className={className}
            onKeyDown={(event) => event.key === 'Escape' && setOpen(false)}
        >
            <form role="search" onSubmit={submit} className="sf-searchbar">
                <select
                    aria-label="Category"
                    value={category}
                    onChange={(event) => setCategory(event.target.value)}
                >
                    <option value="">All categories</option>
                    {(storefrontCategories ?? []).map((item) => (
                        <option key={item.slug} value={item.slug}>
                            {item.name}
                        </option>
                    ))}
                </select>
                <input
                    type="search"
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    onFocus={() => results && setOpen(true)}
                    placeholder="Search products, shops, suppliers or categories"
                    aria-label="Search"
                    aria-expanded={showDropdown}
                    aria-controls={listId}
                    autoComplete="off"
                />
                <button type="submit">Search</button>
            </form>
            {showDropdown && (
                <div
                    id={listId}
                    className="absolute top-full right-0 left-0 z-40 mt-1 max-h-[70vh] overflow-y-auto rounded-lg border border-[var(--sf-line)] bg-[var(--sf-surface)] p-2 text-sm shadow-[var(--sf-shadow)]"
                >
                    {results.total === 0 ? (
                        <p className="p-3 text-[var(--sf-muted)]">
                            Nothing matches “{query.trim()}”.
                        </p>
                    ) : (
                        <>
                            <SuggestionGroup title="Wholesale products">
                                {results.products.map((product) => (
                                    <button
                                        key={`p${product.id}`}
                                        type="button"
                                        className="flex w-full items-center justify-between gap-3 rounded px-2 py-1.5 text-left hover:bg-[var(--sf-surface-2)]"
                                        onClick={() => {
                                            setOpen(false);
                                            openProduct(product.slug);
                                        }}
                                    >
                                        <span className="truncate">
                                            {product.name}
                                        </span>
                                        <span className="shrink-0 text-xs text-[var(--sf-muted)]">
                                            {product.from_price ??
                                                product.supplier.name}
                                        </span>
                                    </button>
                                ))}
                            </SuggestionGroup>
                            <SuggestionGroup title="Local shop items">
                                {results.items.map((item) => (
                                    <SuggestionLink
                                        key={`i${item.id}`}
                                        href={shopShow.url(item.shop.slug)}
                                        label={item.name}
                                        hint={`${item.price} · ${item.shop.name}`}
                                    />
                                ))}
                            </SuggestionGroup>
                            <SuggestionGroup title="Local shops">
                                {results.shops.map((shop) => (
                                    <SuggestionLink
                                        key={`s${shop.id}`}
                                        href={shopShow.url(shop.slug)}
                                        label={shop.name}
                                        hint={shop.city ?? ''}
                                    />
                                ))}
                            </SuggestionGroup>
                            <SuggestionGroup title="Suppliers">
                                {results.suppliers.map((supplier) => (
                                    <SuggestionLink
                                        key={`u${supplier.id}`}
                                        href={supplierShow.url(supplier.slug)}
                                        label={supplier.name}
                                        hint={supplier.city ?? ''}
                                    />
                                ))}
                            </SuggestionGroup>
                            <SuggestionGroup title="Categories">
                                {results.categories.map((item) => (
                                    <SuggestionLink
                                        key={`c${item.id}`}
                                        href={productsIndex.url({
                                            query: { category: item.slug },
                                        })}
                                        label={item.name}
                                        hint={item.parent ?? ''}
                                    />
                                ))}
                            </SuggestionGroup>
                            <Link
                                href={searchRoute.url({
                                    query: { q: query.trim() },
                                })}
                                className="mt-1 block rounded px-2 py-2 text-center font-semibold text-[var(--sf-accent)] hover:bg-[var(--sf-surface-2)]"
                                onClick={() => setOpen(false)}
                            >
                                See all results
                            </Link>
                        </>
                    )}
                </div>
            )}
        </div>
    );
}

function SuggestionGroup({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode[];
}) {
    if (children.length === 0) {
        return null;
    }

    return (
        <div className="py-1">
            <p className="sf-mono px-2 pb-1 text-[11px] tracking-wide text-[var(--sf-muted)] uppercase">
                {title}
            </p>
            {children}
        </div>
    );
}

function SuggestionLink({
    href,
    label,
    hint,
}: {
    href: string;
    label: string;
    hint: string;
}) {
    return (
        <Link
            href={href}
            className="flex items-center justify-between gap-3 rounded px-2 py-1.5 hover:bg-[var(--sf-surface-2)]"
        >
            <span className="truncate">{label}</span>
            {hint && (
                <span className="shrink-0 text-xs text-[var(--sf-muted)]">
                    {hint}
                </span>
            )}
        </Link>
    );
}
