import { Head } from '@inertiajs/react';
import { BillingPanel } from '@/components/billing-panel';
import Heading from '@/components/heading';
import { edit } from '@/routes/company';
import billing from '@/routes/company/billing';
import type { BillingOverview, CompanySummary } from '@/types';

export default function CompanyBilling({
    company,
    ...overview
}: BillingOverview & { company: CompanySummary }) {
    return (
        <>
            <Head title="Plan & billing" />
            <div className="mx-auto w-full max-w-6xl p-4">
                <Heading
                    title="Plan & billing"
                    description={`Your seller plan for ${company.name} decides how many products you can list, how many quotes you can send and the commission you pay.`}
                />
                <BillingPanel
                    overview={overview}
                    subscribeRoute={billing.subscribe()}
                    cancelRoute={billing.cancel()}
                    payInvoiceRoute={(invoice) => billing.invoices.pay(invoice)}
                />
            </div>
        </>
    );
}

CompanyBilling.layout = {
    breadcrumbs: [
        { title: 'My company', href: edit() },
        { title: 'Plan & billing', href: billing.show() },
    ],
};
