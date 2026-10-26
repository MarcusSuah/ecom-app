import { router } from '@inertiajs/react';
import { Check, FileDown } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate, formatLimit } from '@/lib/format';
import type { BillingOverview, Plan } from '@/types';
import { pdf as invoicePdf } from '@/routes/billing/invoices';

type RouteTarget = { url: string };

/**
 * Current plan, available plans and invoices for one subscriber (a
 * company or a marketplace). The caller supplies the endpoints.
 */
export function BillingPanel({
    overview,
    subscribeRoute,
    cancelRoute,
    payInvoiceRoute,
}: {
    overview: BillingOverview;
    subscribeRoute: RouteTarget;
    cancelRoute: RouteTarget;
    payInvoiceRoute: (invoiceId: number) => RouteTarget;
}) {
    const { subscription, effectivePlan, plans, invoices } = overview;
    const [processingPlan, setProcessingPlan] = useState<number | null>(null);
    const [confirmCancel, setConfirmCancel] = useState(false);

    const choose = (plan: Plan) => {
        setProcessingPlan(plan.id);
        router.post(
            subscribeRoute.url,
            { plan_id: plan.id },
            {
                preserveScroll: true,
                onFinish: () => setProcessingPlan(null),
            },
        );
    };

    const isCurrent = (plan: Plan) =>
        effectivePlan?.id === plan.id && subscription?.status !== 'cancelled';

    return (
        <div className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle className="flex flex-wrap items-center gap-2">
                        {effectivePlan ? effectivePlan.name : 'No plan'}
                        {subscription && (
                            <StatusBadge status={subscription.status} />
                        )}
                    </CardTitle>
                    <CardDescription>
                        {!subscription &&
                            effectivePlan &&
                            'You are on the default plan. Choose another plan below to upgrade.'}
                        {!subscription &&
                            !effectivePlan &&
                            'There is no active plan. Choose one below.'}
                        {subscription?.status === 'trialing' &&
                            `Free trial until ${formatDate(subscription.trial_ends_at)}.`}
                        {subscription?.status === 'active' &&
                            `Renews on ${formatDate(subscription.current_period_ends_at)}.`}
                        {subscription?.status === 'past_due' &&
                            'Payment is overdue. Pay the open invoice below to keep this plan.'}
                        {subscription?.status === 'cancelled' &&
                            `Cancelled. You keep this plan until ${formatDate(subscription.ends_at)}.`}
                    </CardDescription>
                </CardHeader>
                {effectivePlan && (
                    <CardContent className="space-y-4">
                        <ul className="grid gap-2 text-sm sm:grid-cols-2">
                            {effectivePlan.limits.map((limit) => (
                                <li
                                    key={limit.key}
                                    className="flex justify-between gap-4 rounded-md border px-3 py-2"
                                >
                                    <span className="text-muted-foreground">
                                        {limit.label}
                                    </span>
                                    <span className="font-medium">
                                        {formatLimit(limit)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                        {subscription?.is_cancellable && (
                            <Button
                                variant="outline"
                                onClick={() => setConfirmCancel(true)}
                            >
                                Cancel plan
                            </Button>
                        )}
                    </CardContent>
                )}
            </Card>

            <section aria-labelledby="plans-heading" className="space-y-3">
                <h2 id="plans-heading" className="text-lg font-semibold">
                    Plans
                </h2>
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {plans.map((plan) => (
                        <Card
                            key={plan.id}
                            className={
                                isCurrent(plan) ? 'border-primary' : undefined
                            }
                        >
                            <CardHeader>
                                <CardTitle>{plan.name}</CardTitle>
                                <CardDescription>
                                    {plan.description}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex h-full flex-col gap-4">
                                <p className="text-2xl font-semibold">
                                    {plan.formatted_price}
                                </p>
                                {plan.trial_days > 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        {plan.trial_days}-day free trial for
                                        first-time subscribers
                                    </p>
                                )}
                                <ul className="space-y-1 text-sm">
                                    {plan.limits.map((limit) => (
                                        <li
                                            key={limit.key}
                                            className="flex items-center gap-2"
                                        >
                                            <Check
                                                className="size-4 text-emerald-600"
                                                aria-hidden="true"
                                            />
                                            <span>
                                                {limit.label}:{' '}
                                                {formatLimit(limit)}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                                <Button
                                    className="mt-auto"
                                    disabled={
                                        isCurrent(plan) ||
                                        processingPlan !== null
                                    }
                                    onClick={() => choose(plan)}
                                >
                                    {isCurrent(plan)
                                        ? 'Current plan'
                                        : processingPlan === plan.id
                                          ? 'Processing…'
                                          : `Choose ${plan.name}`}
                                </Button>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </section>

            <section aria-labelledby="invoices-heading" className="space-y-3">
                <h2 id="invoices-heading" className="text-lg font-semibold">
                    Invoices
                </h2>
                {invoices.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No invoices yet.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Invoice</TableHead>
                                    <TableHead>Period</TableHead>
                                    <TableHead>Amount</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">
                                        Action
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {invoices.map((invoice) => (
                                    <TableRow key={invoice.id}>
                                        <TableCell className="font-mono text-xs">
                                            {invoice.number}
                                        </TableCell>
                                        <TableCell>
                                            {formatDate(
                                                invoice.period_starts_at,
                                            )}{' '}
                                            –{' '}
                                            {formatDate(invoice.period_ends_at)}
                                        </TableCell>
                                        <TableCell>{invoice.amount}</TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                status={invoice.status}
                                            />
                                        </TableCell>
                                        <TableCell className="space-x-2 text-right whitespace-nowrap">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                asChild
                                            >
                                                <a
                                                    href={invoicePdf.url(
                                                        invoice.id,
                                                    )}
                                                    aria-label={`Download invoice ${invoice.number} as PDF`}
                                                >
                                                    <FileDown aria-hidden="true" />
                                                    PDF
                                                </a>
                                            </Button>
                                            {invoice.status === 'pending' && (
                                                <Button
                                                    size="sm"
                                                    onClick={() =>
                                                        router.post(
                                                            payInvoiceRoute(
                                                                invoice.id,
                                                            ).url,
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    Pay now
                                                </Button>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </section>

            <ConfirmDialog
                open={confirmCancel}
                onOpenChange={setConfirmCancel}
                title="Cancel this plan?"
                description="It will not renew. You keep it until the end of the period you have paid for."
                confirmLabel="Cancel plan"
                onConfirm={() => {
                    router.post(cancelRoute.url, {}, { preserveScroll: true });
                    setConfirmCancel(false);
                }}
            />
        </div>
    );
}
