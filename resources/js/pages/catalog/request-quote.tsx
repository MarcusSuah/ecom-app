import { Head, Link } from '@inertiajs/react';
import {
    RfqForm,
    type RfqFormDefaults,
    type RfqFormOptions,
    RfqSteps,
} from '@/components/rfq-form';
import { show as productShow } from '@/routes/products';

type Props = RfqFormOptions & {
    defaults: RfqFormDefaults;
    product: { name: string; slug: string; moq: number } | null;
};

export default function RequestQuote({
    defaults,
    product,
    categories,
    units,
}: Props) {
    return (
        <>
            <Head title="Request quotes" />
            <div className="grid gap-8 lg:grid-cols-[1fr_22rem]">
                <section aria-labelledby="rfq-heading" className="space-y-6">
                    <div>
                        <h1 id="rfq-heading" className="text-2xl font-semibold">
                            One request, several quotes.
                        </h1>
                        <p className="text-muted-foreground">
                            Requests stay open for 14 days.
                        </p>
                    </div>

                    {product && (
                        <p className="text-sm">
                            Started from{' '}
                            <Link
                                href={productShow(product.slug)}
                                className="font-medium text-primary hover:underline"
                            >
                                {product.name}
                            </Link>{' '}
                            (minimum order {product.moq.toLocaleString()}). Its
                            supplier is always included if verified.
                        </p>
                    )}

                    <RfqForm
                        defaults={defaults}
                        categories={categories}
                        units={units}
                    />
                </section>

                <aside
                    aria-labelledby="how-heading"
                    className="space-y-4 rounded-lg border p-4"
                >
                    <h2 id="how-heading" className="font-semibold">
                        How it works
                    </h2>
                    <RfqSteps />
                </aside>
            </div>
        </>
    );
}
