import { Form, Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { type OrderDetailData, OrderDetail } from '@/components/order-detail';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import sellerOrders from '@/routes/seller/orders';

export default function SellerOrderShow({
    order,
    protectionLabel,
    canRefund,
    canCollectCash,
    canCancelCash,
}: {
    order: OrderDetailData;
    protectionLabel: string;
    canRefund: boolean;
    canCollectCash: boolean;
    canCancelCash: boolean;
}) {
    const errors = usePage().props.errors as Record<string, string | undefined>;
    const [cancelling, setCancelling] = useState(false);
    const move = (status: string) =>
        router.patch(
            sellerOrders.status.url(order.id),
            { status },
            { preserveScroll: true },
        );

    return (
        <>
            <Head title={`Order ${order.number}`} />
            <div className="mx-auto w-full max-w-4xl space-y-6 p-4">
                <OrderDetail
                    order={order}
                    party="seller"
                    protectionLabel={protectionLabel}
                />
                <InputError message={errors.status ?? errors.order} />

                <div className="flex flex-wrap gap-2">
                    {order.seller_steps.map((step) => (
                        <Button
                            key={step.value}
                            onClick={() => move(step.value)}
                        >
                            Mark as {step.label.toLowerCase()}
                        </Button>
                    ))}
                    {canCollectCash && (
                        <Button
                            onClick={() =>
                                router.post(
                                    sellerOrders.cashCollected.url(order.id),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Cash received ({order.total}), handed over
                        </Button>
                    )}
                    {(canRefund || canCancelCash) && !cancelling && (
                        <Button
                            variant="outline"
                            onClick={() => setCancelling(true)}
                        >
                            {canCancelCash
                                ? 'Cancel order'
                                : 'Cancel and refund'}
                        </Button>
                    )}
                </div>
                {order.payment_method === 'cash_on_pickup' &&
                    !['completed', 'cancelled'].includes(order.status) && (
                        <p className="text-sm text-muted-foreground">
                            The buyer pays {order.total} in cash when collecting
                            the order. Mark it ready for pickup, then confirm
                            the cash at handover.
                        </p>
                    )}
                {order.status === 'delivered' && (
                    <p className="text-sm text-muted-foreground">
                        Waiting for the buyer to confirm receipt; the payment is
                        then released to you.
                    </p>
                )}

                {cancelling && (
                    <Form
                        {...(canCancelCash
                            ? sellerOrders.cancel.form(order.id)
                            : sellerOrders.refund.form(order.id))}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setCancelling(false)}
                        className="space-y-3 rounded-lg border p-4"
                    >
                        {({ processing, errors: formErrors }) => (
                            <>
                                <div className="grid gap-1">
                                    <Label htmlFor="reason">
                                        Why can you not fulfil this order?
                                    </Label>
                                    <Textarea
                                        id="reason"
                                        name="reason"
                                        rows={3}
                                        required
                                    />
                                    <InputError
                                        message={
                                            formErrors.reason ??
                                            formErrors.order
                                        }
                                    />
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {canCancelCash
                                        ? 'Nothing was paid, so the order is simply closed and the buyer told.'
                                        : 'The buyer gets their money back and the order is closed.'}
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        disabled={processing}
                                    >
                                        {canCancelCash
                                            ? 'Cancel order'
                                            : 'Cancel and refund'}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() => setCancelling(false)}
                                    >
                                        Back
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                )}
            </div>
        </>
    );
}

SellerOrderShow.layout = {
    breadcrumbs: [{ title: 'Orders received', href: sellerOrders.index() }],
};
