import { Head } from '@inertiajs/react';
import { BillingPanel } from '@/components/billing-panel';
import Heading from '@/components/heading';
import billing from '@/routes/admin/billing';
import type { BillingOverview } from '@/types';

export default function AdminBilling({
    marketplace,
    ...overview
}: BillingOverview & {
    marketplace: { name: string; status: string; is_system: boolean };
}) {
    return (
        <>
            <Head title="Platform plan" />
            <div className="mx-auto w-full max-w-6xl p-4">
                <Heading
                    title="Platform plan"
                    description={`What ${marketplace.name} pays the platform. Without a current plan the marketplace is closed to visitors.`}
                />
                {marketplace.is_system ? (
                    <p className="rounded-md border p-6 text-sm text-muted-foreground">
                        {marketplace.name} is the platform's own marketplace. It
                        is always open and does not need a platform plan.
                    </p>
                ) : (
                    <BillingPanel
                        overview={overview}
                        subscribeRoute={billing.subscribe()}
                        cancelRoute={billing.cancel()}
                        payInvoiceRoute={(invoice) =>
                            billing.invoices.pay(invoice)
                        }
                    />
                )}
            </div>
        </>
    );
}

AdminBilling.layout = {
    breadcrumbs: [{ title: 'Platform plan', href: billing.show() }],
};
