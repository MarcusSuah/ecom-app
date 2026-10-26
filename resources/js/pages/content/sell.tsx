import { Link, usePage } from '@inertiajs/react';
import { ApproxPrice } from '@/components/approx-price';
import { ContentPage, type ContentPageProps } from '@/components/content-page';
import { Button } from '@/components/ui/button';
import { formatLimit } from '@/lib/format';
import { register } from '@/routes';
import { create as createCompany } from '@/routes/company';
import { show as contentPage } from '@/routes/pages';
import type { Plan, SharedData } from '@/types';

type Props = ContentPageProps & {
    plans: (Plan & { approx_price: string | null })[];
};

export default function Sell({ title, facts, plans }: Props) {
    const { auth, tenant } = usePage<SharedData>().props;
    const start = auth.user ? createCompany() : register();

    return (
        <ContentPage
            title={title}
            current="sell"
            protectionLabel={facts.protectionLabel}
            lead={`Reach buyers on ${tenant?.name ?? 'the marketplace'}, whether you supply in bulk or run a shop near them.`}
        >
            <section>
                <h2>Two ways to sell</h2>
                <ul>
                    <li>
                        <strong>Wholesale supplier.</strong> List products with
                        tier prices, a minimum order quantity (MOQ) and lead
                        time. Buyers order at your listed price or send requests
                        for quotes that you answer.
                    </li>
                    <li>
                        <strong>Local shop.</strong> List everyday items with
                        stock, set your delivery fee, radius and opening hours,
                        and take pickup or delivery orders from people nearby.
                    </li>
                </ul>
            </section>

            <section>
                <h2>How you get paid</h2>
                <p>
                    Buyers pay through the marketplace and the money is held
                    under {facts.protectionLabel}. It is released to you when
                    the buyer confirms they received the order, or automatically{' '}
                    {facts.autoReleaseDays} days after delivery if there is no
                    dispute. Your payout is the order total minus your plan's
                    commission ({facts.defaultCommissionPercent}% unless your
                    plan says otherwise).
                </p>
            </section>

            <section aria-labelledby="plans-heading">
                <h2 id="plans-heading">Seller plans</h2>
                {plans.length === 0 ? (
                    <p className="text-muted-foreground">
                        Plans will be announced soon. You can start listing on
                        the free tier today.
                    </p>
                ) : (
                    <ul className="!ml-0 grid !list-none gap-4 sm:grid-cols-2">
                        {plans.map((plan) => (
                            <li
                                key={plan.id}
                                className="!ml-0 flex flex-col gap-2 rounded-lg border p-4"
                            >
                                <div className="flex items-baseline justify-between gap-2">
                                    <h3 className="font-semibold">
                                        {plan.name}
                                    </h3>
                                    {plan.is_default && (
                                        <span className="text-xs text-muted-foreground">
                                            Everyone starts here
                                        </span>
                                    )}
                                </div>
                                <p>
                                    <span className="text-xl font-semibold">
                                        {plan.formatted_price}
                                    </span>{' '}
                                    <ApproxPrice value={plan.approx_price} />
                                </p>
                                {plan.description && (
                                    <p className="text-sm text-muted-foreground">
                                        {plan.description}
                                    </p>
                                )}
                                <dl className="mt-auto grid gap-1 border-t pt-2 text-sm">
                                    {plan.limits.map((limit) => (
                                        <div
                                            key={limit.key}
                                            className="flex justify-between gap-2"
                                        >
                                            <dt className="text-muted-foreground">
                                                {limit.label}
                                            </dt>
                                            <dd>
                                                {limit.key ===
                                                'commission_percent'
                                                    ? `${limit.value ?? facts.defaultCommissionPercent}%`
                                                    : formatLimit(limit)}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section>
                <h2>Start in three steps</h2>
                <ol>
                    <li>Create an account and add your company.</li>
                    <li>
                        <Link
                            href={contentPage('get-verified')}
                            className="text-primary underline-offset-4 hover:underline"
                        >
                            Get verified
                        </Link>{' '}
                        so buyers see the badge and you receive requests for
                        quotes.
                    </li>
                    <li>
                        List your products or shop items and start taking
                        orders.
                    </li>
                </ol>
                <Button asChild className="mt-4">
                    <Link href={start}>
                        {auth.user ? 'Add your company' : 'Create an account'}
                    </Link>
                </Button>
            </section>
        </ContentPage>
    );
}
