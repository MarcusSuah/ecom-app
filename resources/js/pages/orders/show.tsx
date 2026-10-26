import { Form, Head, router, usePage, usePoll } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import { ProofPhotosInput } from '@/components/proof-photos-input';
import { type OrderDetailData, OrderDetail } from '@/components/order-detail';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import orderRoutes from '@/routes/orders';

type Props = {
    order: OrderDetailData;
    protectionLabel: string;
    paymentProviders: { value: string; label: string }[];
    disputeReasons: { value: string; label: string }[];
    autoReleaseDays: number;
};

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';

export default function OrderShow({
    order,
    protectionLabel,
    paymentProviders,
    disputeReasons,
    autoReleaseDays,
}: Props) {
    const errors = usePage().props.errors as Record<string, string | undefined>;
    const [provider, setProvider] = useState(paymentProviders[0]?.value ?? '');
    const [confirming, setConfirming] = useState(false);
    const [disputing, setDisputing] = useState(false);
    const awaitingConfirmation =
        order.payment?.status === 'initiated' &&
        order.status === 'pending_payment';
    const handedOver = [
        'ready_for_pickup',
        'out_for_delivery',
        'shipped',
        'delivered',
    ].includes(order.status);
    const canDispute = order.payment?.status === 'held' && !order.dispute;

    // While a Mobile Money payment waits for approval on the phone, check for the webhook's result.
    usePoll(5000, { only: ['order'] }, { autoStart: awaitingConfirmation });

    return (
        <>
            <Head title={`Order ${order.number}`} />
            <div className="mx-auto w-full max-w-4xl space-y-6 p-4">
                <OrderDetail
                    order={order}
                    party="buyer"
                    protectionLabel={protectionLabel}
                />
                <InputError message={errors.status ?? errors.order} />

                {order.payment_method === 'cash_on_pickup' &&
                    !['completed', 'cancelled'].includes(order.status) && (
                        <section
                            aria-labelledby="cash-heading"
                            className="space-y-2 rounded-lg border border-amber-500/40 bg-amber-50 p-4 dark:bg-amber-950/40"
                        >
                            <h2 id="cash-heading" className="font-semibold">
                                Pay {order.total} in cash at pickup
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                The shop prepares your order and tells you when
                                it is ready. Pay when you collect it; your
                                receipt is emailed once the shop records the
                                cash.
                            </p>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() =>
                                    router.post(
                                        orderRoutes.cancel.url(order.id),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Cancel this order
                            </Button>
                        </section>
                    )}

                {order.status === 'pending_payment' &&
                    order.payment_method === 'online' && (
                        <section
                            aria-labelledby="pay-heading"
                            className="space-y-3 rounded-lg border p-4"
                        >
                            <h2 id="pay-heading" className="font-semibold">
                                Pay for this order
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {protectionLabel}: the seller gets the money
                                only after you confirm you received the order,
                                or {autoReleaseDays} days after delivery if you
                                report no problem.
                            </p>
                            {awaitingConfirmation && (
                                <p
                                    role="status"
                                    className="rounded-md bg-muted p-2 text-sm"
                                >
                                    Waiting for the payment to be confirmed…
                                </p>
                            )}
                            <Form
                                {...orderRoutes.pay.form(order.id)}
                                options={{ preserveScroll: true }}
                                className="grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end"
                            >
                                {({ processing, errors: payErrors }) => (
                                    <>
                                        <div className="grid gap-1">
                                            <Label htmlFor="provider">
                                                Pay with
                                            </Label>
                                            <select
                                                id="provider"
                                                name="provider"
                                                className={selectClass}
                                                value={provider}
                                                onChange={(event) =>
                                                    setProvider(
                                                        event.target.value,
                                                    )
                                                }
                                            >
                                                {paymentProviders.map(
                                                    (option) => (
                                                        <option
                                                            key={option.value}
                                                            value={option.value}
                                                        >
                                                            {option.label}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                            <InputError
                                                message={payErrors.provider}
                                            />
                                        </div>
                                        {provider === 'mtn_momo' ? (
                                            <div className="grid gap-1">
                                                <Label htmlFor="phone">
                                                    Mobile Money number
                                                </Label>
                                                <Input
                                                    id="phone"
                                                    name="phone"
                                                    type="tel"
                                                    placeholder="+250788123456"
                                                    defaultValue={
                                                        order.buyer.phone ?? ''
                                                    }
                                                />
                                                <InputError
                                                    message={payErrors.phone}
                                                />
                                            </div>
                                        ) : (
                                            <div />
                                        )}
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Starting…'
                                                : `Pay ${order.total}`}
                                        </Button>
                                    </>
                                )}
                            </Form>
                            {order.can_pay_cash && !awaitingConfirmation && (
                                <div className="flex flex-wrap items-center gap-3 rounded-md border border-dashed p-3">
                                    <p className="flex-1 text-sm">
                                        <span className="font-medium">
                                            Prefer cash?
                                        </span>{' '}
                                        Pay the shop when you collect the order.
                                    </p>
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            router.post(
                                                orderRoutes.payCash.url(
                                                    order.id,
                                                ),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        Pay cash on pickup
                                    </Button>
                                </div>
                            )}
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() =>
                                    router.post(
                                        orderRoutes.cancel.url(order.id),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Cancel this order
                            </Button>
                        </section>
                    )}

                {(handedOver && order.payment?.status === 'held') ||
                canDispute ? (
                    <div className="flex flex-wrap gap-2">
                        {handedOver &&
                            order.payment?.status === 'held' &&
                            !order.dispute && (
                                <Button onClick={() => setConfirming(true)}>
                                    I received this order
                                </Button>
                            )}
                        {canDispute && (
                            <Button
                                variant="outline"
                                onClick={() => setDisputing(true)}
                            >
                                Report a problem
                            </Button>
                        )}
                    </div>
                ) : null}

                {disputing && (
                    <section
                        aria-labelledby="dispute-form-heading"
                        className="space-y-3 rounded-lg border p-4"
                    >
                        <h2 id="dispute-form-heading" className="font-semibold">
                            Report a problem
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            The payment stays held while our team looks into it.
                        </p>
                        <Form
                            {...orderRoutes.dispute.form(order.id)}
                            options={{ preserveScroll: true }}
                            onSuccess={() => setDisputing(false)}
                            className="space-y-3"
                        >
                            {({ processing, errors: disputeErrors }) => (
                                <>
                                    <div className="grid gap-1">
                                        <Label htmlFor="reason">
                                            What went wrong?
                                        </Label>
                                        <select
                                            id="reason"
                                            name="reason"
                                            className={selectClass}
                                        >
                                            {disputeReasons.map((reason) => (
                                                <option
                                                    key={reason.value}
                                                    value={reason.value}
                                                >
                                                    {reason.label}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={disputeErrors.reason}
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="details">Details</Label>
                                        <Textarea
                                            id="details"
                                            name="details"
                                            rows={4}
                                            required
                                        />
                                        <InputError
                                            message={disputeErrors.details}
                                        />
                                    </div>
                                    <ProofPhotosInput
                                        max={6}
                                        suggested={3}
                                        errors={Object.entries(disputeErrors)
                                            .filter(([key]) =>
                                                key.startsWith('photos'),
                                            )
                                            .map(([, message]) => message)}
                                    />
                                    <div className="flex gap-2">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Open dispute
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            onClick={() => setDisputing(false)}
                                        >
                                            Cancel
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </section>
                )}
            </div>

            <ConfirmDialog
                open={confirming}
                onOpenChange={setConfirming}
                title="Confirm you received this order?"
                description="The payment is released to the seller and can no longer be disputed."
                confirmLabel="Yes, release payment"
                onConfirm={() => {
                    router.post(
                        orderRoutes.confirmReceipt.url(order.id),
                        {},
                        { preserveScroll: true },
                    );
                    setConfirming(false);
                }}
            />
        </>
    );
}

OrderShow.layout = {
    breadcrumbs: [{ title: 'My orders', href: orderRoutes.index() }],
};
