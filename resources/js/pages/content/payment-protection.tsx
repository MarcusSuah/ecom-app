import { ShieldCheck } from 'lucide-react';
import { ContentPage, type ContentPageProps } from '@/components/content-page';

export default function PaymentProtection({
    title,
    facts,
    disputeReasons,
}: ContentPageProps) {
    return (
        <ContentPage
            title={title}
            current="payment-protection"
            protectionLabel={facts.protectionLabel}
            lead="Your money is only paid to the seller once you have what you ordered."
        >
            <section className="flex gap-3 rounded-lg border border-emerald-600/30 bg-emerald-50 p-4 dark:bg-emerald-950">
                <ShieldCheck
                    className="mt-0.5 size-5 shrink-0 text-emerald-700 dark:text-emerald-300"
                    aria-hidden="true"
                />
                <p className="text-sm">
                    Pay through the marketplace only. Payments made directly to
                    a seller, outside the order page, are not covered by{' '}
                    {facts.protectionLabel}.
                </p>
            </section>

            <section>
                <h2>How it works</h2>
                <ol>
                    <li>
                        You pay for the order with Mobile Money or card. The
                        payment is held; the seller can see it is secured.
                    </li>
                    <li>
                        The seller prepares and sends your order, and you follow
                        each step on the order page.
                    </li>
                    <li>
                        When it arrives, press "I received this order" and the
                        seller is paid.
                    </li>
                    <li>
                        If you do nothing, the payment is released automatically{' '}
                        {facts.autoReleaseDays} days after the order is marked
                        delivered.
                    </li>
                </ol>
            </section>

            <section>
                <h2>If something is wrong</h2>
                <p>
                    Open a dispute from the order page before the payment is
                    released. You can report:
                </p>
                <ul>
                    {disputeReasons.map((reason) => (
                        <li key={reason}>{reason}</li>
                    ))}
                </ul>
                <p>
                    While a dispute is open the money stays held, and automatic
                    release is paused. The marketplace team looks at the order,
                    messages and any delivery proof, then either pays the seller
                    or refunds you in full.
                </p>
            </section>

            <section>
                <h2>Refunds</h2>
                <p>
                    Refunds go back to the account you paid from. A seller who
                    cancels a paid order refunds you in full, and stock is put
                    back. Prices may be shown approximately in another currency,
                    but you are always charged and refunded in the order's own
                    currency.
                </p>
            </section>
        </ContentPage>
    );
}
