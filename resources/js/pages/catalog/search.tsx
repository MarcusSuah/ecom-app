import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { ProductCard } from '@/components/product-card';
import { VerifiedMark } from '@/components/storefront/verified-mark';
import { useLiveSearch } from '@/hooks/use-live-search';
import { hueStyle, initials } from '@/lib/hue';
import { search as searchRoute } from '@/routes';
import { index as productsIndex } from '@/routes/products';
import { show as shopShow } from '@/routes/shops';
import { show as supplierShow } from '@/routes/suppliers';
import type { SearchResults } from '@/types/search';

function Group({
    id,
    title,
    count,
    children,
}: {
    id: string;
    title: string;
    count: number;
    children: ReactNode;
}) {
    if (count === 0) {
        return null;
    }

    return (
        <section aria-labelledby={id} className="space-y-3 pt-8">
            <h2 id={id} className="sf-display text-xl font-bold">
                {title}{' '}
                <span className="sf-mono text-sm font-normal text-[var(--sf-muted)]">
                    {count}
                </span>
            </h2>
            {children}
        </section>
    );
}

/**
 * Results for one search across wholesale products, local shop items,
 * shops, suppliers and categories. Typing searches again.
 */
export default function Search({
    query,
    results,
    minLength,
}: {
    query: string;
    results: SearchResults;
    minLength: number;
}) {
    const [text, setText] = useState(query);

    useLiveSearch(text, (value) =>
        router.get(searchRoute.url(), value.trim() ? { q: value.trim() } : {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }),
    );

    return (
        <>
            <Head title={query ? `Search: ${query}` : 'Search'} />
            <div className="mx-auto max-w-[1240px] px-4 py-8">
                <h1 className="sf-display text-3xl font-extrabold">Search</h1>
                <form
                    role="search"
                    onSubmit={(event) => event.preventDefault()}
                    className="mt-4"
                >
                    <label htmlFor="search-page-q" className="sr-only">
                        Search the marketplace
                    </label>
                    <input
                        id="search-page-q"
                        type="search"
                        value={text}
                        onChange={(event) => setText(event.target.value)}
                        placeholder="Products, shops, suppliers or categories"
                        className="h-12 w-full rounded-lg border border-[var(--sf-line)] bg-[var(--sf-surface)] px-4 text-base"
                        autoFocus
                    />
                </form>
                <p
                    className="mt-3 text-sm text-[var(--sf-muted)]"
                    aria-live="polite"
                >
                    {query.length < minLength
                        ? `Type at least ${minLength} letters.`
                        : results.total === 0
                          ? `Nothing matches “${query}”. Try another word, or post a request and suppliers will come to you.`
                          : `${results.total} results for “${query}”.`}
                </p>

                <Group
                    id="search-products"
                    title="Wholesale products"
                    count={results.products.length}
                >
                    <ul className="grid grid-cols-[repeat(auto-fill,minmax(230px,1fr))] gap-3.5">
                        {results.products.map((product) => (
                            <li key={product.id}>
                                <ProductCard product={product} />
                            </li>
                        ))}
                    </ul>
                    <Link
                        href={productsIndex({ query: { q: query } })}
                        className="sf-btn sf-btn-sm"
                    >
                        All matching products with filters
                    </Link>
                </Group>

                <Group
                    id="search-items"
                    title="Local shop items"
                    count={results.items.length}
                >
                    <ul className="grid grid-cols-[repeat(auto-fill,minmax(230px,1fr))] gap-3.5">
                        {results.items.map((item) => (
                            <li key={item.id}>
                                <Link
                                    href={shopShow(item.shop.slug)}
                                    className="sf-card h-full"
                                >
                                    <span
                                        className="sf-thumb"
                                        style={hueStyle(item.category)}
                                    >
                                        {item.image_url && (
                                            <img
                                                src={item.image_url}
                                                alt=""
                                                loading="lazy"
                                            />
                                        )}
                                        <span className="sf-tag">
                                            {item.category}
                                        </span>
                                    </span>
                                    <span className="flex flex-col gap-1 px-3.5 pt-3 pb-3.5">
                                        <b className="leading-tight">
                                            {item.name}
                                        </b>
                                        <span className="sf-price">
                                            {item.price}
                                        </span>
                                        <span className="sf-meta">
                                            {item.shop.name} ·{' '}
                                            {item.stock_label}
                                        </span>
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </Group>

                <Group
                    id="search-shops"
                    title="Local shops"
                    count={results.shops.length}
                >
                    <CompanyList
                        companies={results.shops}
                        href={(slug) => shopShow.url(slug)}
                    />
                </Group>

                <Group
                    id="search-suppliers"
                    title="Suppliers"
                    count={results.suppliers.length}
                >
                    <CompanyList
                        companies={results.suppliers}
                        href={(slug) => supplierShow.url(slug)}
                    />
                </Group>

                <Group
                    id="search-categories"
                    title="Categories"
                    count={results.categories.length}
                >
                    <ul className="flex flex-wrap gap-2">
                        {results.categories.map((category) => (
                            <li key={category.id}>
                                <Link
                                    href={productsIndex({
                                        query: { category: category.slug },
                                    })}
                                    className="sf-pill hover:underline"
                                >
                                    {category.parent
                                        ? `${category.parent} › `
                                        : ''}
                                    {category.name}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </Group>
            </div>
        </>
    );
}

function CompanyList({
    companies,
    href,
}: {
    companies: SearchResults['shops'];
    href: (slug: string) => string;
}) {
    return (
        <ul className="grid grid-cols-[repeat(auto-fill,minmax(260px,1fr))] gap-3">
            {companies.map((company) => (
                <li key={company.id}>
                    <Link
                        href={href(company.slug)}
                        className="sf-panel flex items-center gap-3 p-3 hover:border-[var(--sf-accent)]"
                        style={hueStyle(company.slug)}
                    >
                        {company.logo_url ? (
                            <img
                                src={company.logo_url}
                                alt=""
                                className="size-11 rounded-md object-cover"
                            />
                        ) : (
                            <span
                                className="sf-avatar size-11"
                                aria-hidden="true"
                            >
                                {initials(company.name)}
                            </span>
                        )}
                        <span className="min-w-0">
                            <b className="flex items-center gap-1.5 truncate">
                                {company.name}
                                {company.is_verified && <VerifiedMark />}
                            </b>
                            <span className="sf-meta">
                                {[company.city, company.country?.toUpperCase()]
                                    .filter(Boolean)
                                    .join(', ') || company.type_label}
                            </span>
                        </span>
                    </Link>
                </li>
            ))}
        </ul>
    );
}
