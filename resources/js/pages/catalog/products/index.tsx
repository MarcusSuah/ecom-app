import { Head, router } from '@inertiajs/react';
import { useLiveSearch } from '@/hooks/use-live-search';
import { type FormEvent, useState } from 'react';
import { Pagination } from '@/components/pagination';
import { ProductCard } from '@/components/product-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { index as productsIndex } from '@/routes/products';
import type { Paginated, ProductCard as ProductCardData } from '@/types';

type Filters = {
    q: string;
    category: string;
    verified: boolean;
    max_moq: string;
    min_price: string;
    max_price: string;
    currency: string;
    sort: string;
};

type Props = {
    products: Paginated<ProductCardData>;
    filters: Filters;
    categories: { id: number; name: string; slug: string }[];
    currencies: string[];
};

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';

const sorts = [
    { value: 'relevance', label: 'Most relevant' },
    { value: 'price', label: 'Lowest price' },
    { value: 'moq', label: 'Lowest MOQ' },
    { value: 'newest', label: 'Newest' },
];

export default function ProductsIndex({
    products,
    filters,
    categories,
    currencies,
}: Props) {
    const [form, setForm] = useState<Filters>(filters);

    const apply = (next: Filters) => {
        const query = Object.fromEntries(
            Object.entries(next).filter(
                ([, value]) =>
                    value !== '' && value !== false && !(value === 'relevance'),
            ),
        );
        router.get(productsIndex.url(), query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    useLiveSearch(form.q, () => apply(form));

    const submit = (event: FormEvent) => {
        event.preventDefault();
        apply(form);
    };

    const update = <K extends keyof Filters>(
        key: K,
        value: Filters[K],
        applyNow = false,
    ) => {
        const next = { ...form, [key]: value };
        setForm(next);

        if (applyNow) {
            apply(next);
        }
    };

    return (
        <>
            <Head title="Wholesale products" />
            <div className="grid gap-6 lg:grid-cols-[16rem_1fr]">
                <aside aria-labelledby="filters-heading">
                    <h2 id="filters-heading" className="sr-only">
                        Filters
                    </h2>
                    <form
                        onSubmit={submit}
                        className="space-y-4 rounded-lg border p-4"
                    >
                        <div className="grid gap-1.5">
                            <Label htmlFor="category">Category</Label>
                            <select
                                id="category"
                                className={selectClass}
                                value={form.category}
                                onChange={(event) =>
                                    update('category', event.target.value, true)
                                }
                            >
                                <option value="">All categories</option>
                                {categories.map((category) => (
                                    <option
                                        key={category.id}
                                        value={category.slug}
                                    >
                                        {category.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <label className="flex items-center justify-between gap-2 text-sm">
                            Verified suppliers only
                            <Switch
                                checked={form.verified}
                                onCheckedChange={(checked) =>
                                    update('verified', checked, true)
                                }
                            />
                        </label>
                        <div className="grid gap-1.5">
                            <Label htmlFor="max_moq">Max. MOQ</Label>
                            <Input
                                id="max_moq"
                                inputMode="numeric"
                                value={form.max_moq}
                                onChange={(event) =>
                                    update('max_moq', event.target.value)
                                }
                                placeholder="Any"
                            />
                        </div>
                        <fieldset className="grid gap-1.5">
                            <legend className="mb-1.5 text-sm font-medium">
                                Unit price
                            </legend>
                            <div className="grid grid-cols-2 gap-2">
                                <Input
                                    aria-label="Minimum unit price"
                                    inputMode="decimal"
                                    value={form.min_price}
                                    onChange={(event) =>
                                        update('min_price', event.target.value)
                                    }
                                    placeholder="Min"
                                />
                                <Input
                                    aria-label="Maximum unit price"
                                    inputMode="decimal"
                                    value={form.max_price}
                                    onChange={(event) =>
                                        update('max_price', event.target.value)
                                    }
                                    placeholder="Max"
                                />
                            </div>
                            <select
                                aria-label="Price currency"
                                className={selectClass}
                                value={form.currency}
                                onChange={(event) =>
                                    update('currency', event.target.value)
                                }
                            >
                                {currencies.map((currency) => (
                                    <option key={currency}>{currency}</option>
                                ))}
                            </select>
                            <p className="text-xs text-muted-foreground">
                                A price range shows only products priced in this
                                currency.
                            </p>
                        </fieldset>
                        <Button type="submit" className="w-full">
                            Apply filters
                        </Button>
                    </form>
                </aside>

                <section
                    aria-labelledby="results-heading"
                    className="space-y-4"
                >
                    <div className="flex flex-wrap items-end gap-3">
                        <form
                            onSubmit={submit}
                            role="search"
                            className="flex flex-1 gap-2"
                        >
                            <Label htmlFor="q" className="sr-only">
                                Search products
                            </Label>
                            <Input
                                id="q"
                                type="search"
                                value={form.q}
                                onChange={(event) =>
                                    update('q', event.target.value)
                                }
                                placeholder="Search products, e.g. maize flour"
                                className="min-w-0 flex-1"
                            />
                            <Button type="submit">Search</Button>
                        </form>
                        <div className="grid gap-1">
                            <Label htmlFor="sort">Sort by</Label>
                            <select
                                id="sort"
                                className={selectClass}
                                value={form.sort}
                                onChange={(event) =>
                                    update('sort', event.target.value, true)
                                }
                            >
                                {sorts.map((sort) => (
                                    <option key={sort.value} value={sort.value}>
                                        {sort.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>

                    <h1 id="results-heading" className="text-lg font-semibold">
                        {products.total.toLocaleString()}{' '}
                        {products.total === 1 ? 'product' : 'products'}
                    </h1>

                    {products.data.length === 0 ? (
                        <p className="rounded-lg border p-8 text-center text-muted-foreground">
                            No products match these filters.
                        </p>
                    ) : (
                        <ul className="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-4">
                            {products.data.map((product) => (
                                <li key={product.id} className="relative flex">
                                    <ProductCard product={product} />
                                </li>
                            ))}
                        </ul>
                    )}

                    <Pagination page={products} />
                </section>
            </div>
        </>
    );
}
