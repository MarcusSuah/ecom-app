import { Head, Link } from '@inertiajs/react';
import { hueStyle, initials } from '@/lib/hue';
import { index as productsIndex } from '@/routes/products';

type CategoryCount = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    product_count: number;
    children: { name: string; slug: string; product_count: number }[];
};

export default function Categories({
    categories,
}: {
    categories: CategoryCount[];
}) {
    return (
        <>
            <Head title="Wholesale categories" />
            <h1 className="mb-6 text-2xl font-semibold">
                Wholesale categories
            </h1>
            <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {categories.map((category) => (
                    <li
                        key={category.id}
                        className="sf-cat"
                        style={hueStyle(category.slug)}
                    >
                        <span className="sf-ico" aria-hidden="true">
                            {initials(category.name)}
                        </span>
                        <Link
                            href={productsIndex({
                                query: { category: category.slug },
                            })}
                            className="sf-display flex items-baseline justify-between gap-2 text-[17px] font-bold hover:underline"
                        >
                            {category.name}
                            <span className="text-sm font-normal text-muted-foreground">
                                {category.product_count.toLocaleString()}{' '}
                                {category.product_count === 1
                                    ? 'product'
                                    : 'products'}
                            </span>
                        </Link>
                        {category.description && (
                            <p className="mt-1 text-sm text-muted-foreground">
                                {category.description}
                            </p>
                        )}
                        {category.children.length > 0 && (
                            <ul className="mt-3 flex flex-wrap gap-2 text-sm">
                                {category.children.map((child) => (
                                    <li key={child.slug}>
                                        <Link
                                            href={productsIndex({
                                                query: { category: child.slug },
                                            })}
                                            className="rounded-full border px-2 py-0.5 hover:bg-accent"
                                        >
                                            {child.name} ({child.product_count})
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </li>
                ))}
            </ul>
        </>
    );
}
