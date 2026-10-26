import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { type OrderDetailData, OrderDetail } from '@/components/order-detail';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import disputeRoutes from '@/routes/admin/disputes';

type Props = {
    dispute: {
        id: number;
        status: string;
        reason: string;
        details: string;
        resolution_note: string | null;
        resolved_at: string | null;
        created_at: string | null;
    };
    order: OrderDetailData;
    protectionLabel: string;
};

export default function DisputeShow({
    dispute,
    order,
    protectionLabel,
}: Props) {
    return (
        <>
            <Head title={`Dispute · ${order.number}`} />
            <div className="mx-auto grid w-full max-w-6xl gap-8 p-4 lg:grid-cols-[1fr_22rem]">
                <OrderDetail
                    order={order}
                    party="seller"
                    protectionLabel={protectionLabel}
                />
                <aside className="space-y-4">
                    <section className="space-y-2 rounded-lg border p-4 text-sm">
                        <h2 className="font-semibold">Buyer's report</h2>
                        <p className="font-medium">{dispute.reason}</p>
                        <p className="whitespace-pre-line">{dispute.details}</p>
                        <p className="text-muted-foreground">
                            Buyer: {order.buyer.name} (
                            {order.buyer.phone ?? order.buyer.email})
                        </p>
                        <p className="text-muted-foreground">
                            Seller: {order.seller.name}{' '}
                            {order.seller.phone
                                ? `(${order.seller.phone})`
                                : ''}
                        </p>
                    </section>
                    {dispute.status === 'open' ? (
                        <Form
                            {...disputeRoutes.resolve.form(dispute.id)}
                            className="space-y-3 rounded-lg border p-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <h2 className="font-semibold">Decision</h2>
                                    <div className="grid gap-1">
                                        <Label htmlFor="note">
                                            Reason (shown to both sides)
                                        </Label>
                                        <Textarea
                                            id="note"
                                            name="note"
                                            rows={4}
                                            required
                                        />
                                        <InputError
                                            message={
                                                errors.note ??
                                                errors.outcome ??
                                                errors.order
                                            }
                                        />
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        <Button
                                            type="submit"
                                            name="outcome"
                                            value="resolved_release"
                                            disabled={processing}
                                        >
                                            Release to seller
                                        </Button>
                                        <Button
                                            type="submit"
                                            name="outcome"
                                            value="resolved_refund"
                                            variant="destructive"
                                            disabled={processing}
                                        >
                                            Refund buyer
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    ) : (
                        <p className="rounded-lg border p-4 text-sm">
                            Resolved: {dispute.resolution_note}
                        </p>
                    )}
                </aside>
            </div>
        </>
    );
}

DisputeShow.layout = {
    breadcrumbs: [{ title: 'Disputes', href: disputeRoutes.index() }],
};
