import { Head, Link } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import { OpenChatButton } from '@/components/open-chat-button';
import { Pagination } from '@/components/pagination';
import { ProductCard } from '@/components/product-card';
import { StatusBadge } from '@/components/status-badge';
import { VerifiedBadge } from '@/components/verified-badge';
import { formatDate } from '@/lib/format';
import { index as productsIndex } from '@/routes/products';
import { index as suppliersIndex } from '@/routes/suppliers';
import type {
    CompanySummary,
    Paginated,
    ProductCard as ProductCardData,
} from '@/types';

type Props = {
    supplier: CompanySummary & {
        description: string | null;
        member_since: string | null;
        response_time: string | null;
    };
    topCategories: { name: string; slug: string; product_count: number }[];
    products: Paginated<ProductCardData>;
};

export default function SupplierShow({
    supplier,
    topCategories,
    products,
}: Props) {
    return (
        <>
            <Head title={supplier.name} />
            <nav
                aria-label="Breadcrumb"
                className="mb-4 text-sm text-muted-foreground"
            >
                <Link href={suppliersIndex()} className="hover:underline">
                    Suppliers
                </Link>
            </nav>

            <header className="mb-8 flex flex-wrap items-start gap-4">
                {supplier.logo_url ? (
                    <img
                        src={supplier.logo_url}
                        alt=""
                        className="size-20 rounded-lg border object-cover"
                    />
                ) : (
                    <span className="flex size-20 items-center justify-center rounded-lg border text-muted-foreground">
                        <Building2 className="size-8" aria-hidden="true" />
                    </span>
                )}
                <div className="min-w-0 flex-1 space-y-2">
                    <h1 className="flex flex-wrap items-center gap-2 text-2xl font-semibold">
                        {supplier.name}
                        <VerifiedBadge verified={supplier.is_verified} />
                    </h1>
                    <dl className="flex flex-wrap gap-x-6 gap-y-1 text-sm">
                        <div className="flex gap-1">
                            <dt className="text-muted-foreground">Location:</dt>
                            <dd>
                                {[supplier.city, supplier.country]
                                    .filter(Boolean)
                                    .join(', ')}
                            </dd>
                        </div>
                        <div className="flex gap-1">
                            <dt className="text-muted-foreground">
                                Member since:
                            </dt>
                            <dd>{formatDate(supplier.member_since)}</dd>
                        </div>
                        <div className="flex gap-1">
                            <dt className="text-muted-foreground">
                                Response time:
                            </dt>
                            <dd>{supplier.response_time ?? '—'}</dd>
                        </div>
                        <div className="flex items-center gap-1">
                            <dt className="text-muted-foreground">
                                Registration:
                            </dt>
                            <dd>
                                {supplier.verification_status ? (
                                    <StatusBadge
                                        status={supplier.verification_status}
                                    />
                                ) : (
                                    'Not submitted'
                                )}
                            </dd>
                        </div>
                    </dl>
                    <OpenChatButton
                        company={supplier.slug}
                        label="Contact supplier"
                    />
                    {topCategories.length > 0 && (
                        <ul
                            className="flex flex-wrap gap-2 text-sm"
                            aria-label="Top categories"
                        >
                            {topCategories.map((category) => (
                                <li key={category.slug}>
                                    <Link
                                        href={productsIndex({
                                            query: { category: category.slug },
                                        })}
                                        className="rounded-full border px-2 py-0.5 hover:bg-accent"
                                    >
                                        {category.name} (
                                        {category.product_count})
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </header>

            {supplier.description && (
                <p className="mb-8 max-w-3xl text-sm leading-relaxed whitespace-pre-line">
                    {supplier.description}
                </p>
            )}

            <section aria-labelledby="products-heading">
                <h2
                    id="products-heading"
                    className="mb-4 text-lg font-semibold"
                >
                    Products ({products.total})
                </h2>
                {products.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No products listed yet.
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
        </>
    );
}
