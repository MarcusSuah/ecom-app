import { ContentPage, type ContentPageProps } from '@/components/content-page';

export default function SellerGuide({ title, facts }: ContentPageProps) {
    return (
        <ContentPage
            title={title}
            current="seller-guide"
            protectionLabel={facts.protectionLabel}
            lead="Everything a seller does here, from the first listing to the payout."
        >
            <section>
                <h2>1. Listing products</h2>
                <ul>
                    <li>
                        Use a clear name buyers would search for, a category,
                        and real photos.
                    </li>
                    <li>
                        Wholesale: set tier prices (for example 100+ and 1,000+
                        units), the minimum order quantity and the lead time.
                    </li>
                    <li>
                        Shops: keep stock up to date. Items at zero stock are
                        shown as out of stock and cannot be ordered.
                    </li>
                    <li>
                        Your plan sets how many products you can list at once.
                    </li>
                </ul>
            </section>

            <section>
                <h2>2. Answering requests for quotes</h2>
                <p>
                    Verified suppliers in a matching category are told about new
                    requests. A request stays open for {facts.rfqOpenDays} days.
                    Quote with tier prices, lead time and sample terms; quick,
                    complete quotes win more often, and your average response
                    time is shown on your profile.
                </p>
            </section>

            <section>
                <h2>3. Fulfilling orders</h2>
                <p>
                    Orders reach you once the buyer has paid. Move each order
                    through its steps so the buyer is kept informed:
                </p>
                <ul>
                    <li>
                        Local shops: accept, prepare, then mark ready for pickup
                        or out for delivery.
                    </li>
                    <li>Wholesale: mark in production, then shipped.</li>
                </ul>
                <p>
                    If you cannot fulfil a paid order, cancel it with a reason
                    and the buyer is refunded in full.
                </p>
            </section>

            <section>
                <h2>4. Getting paid</h2>
                <p>
                    Payments are held under {facts.protectionLabel} until the
                    buyer confirms receipt, or for {facts.autoReleaseDays} days
                    after delivery. Then a payout is recorded for you: the order
                    total minus your plan's commission (standard{' '}
                    {facts.defaultCommissionPercent}%). See your payouts under{' '}
                    <em>Payouts</em> in your dashboard.
                </p>
            </section>

            <section>
                <h2>5. Disputes</h2>
                <p>
                    If a buyer reports a problem before the payment is released,
                    the money stays held while the marketplace team reviews the
                    order. Reply quickly to the buyer and keep delivery proof:
                    the team decides between paying you and refunding the buyer.
                </p>
            </section>
        </ContentPage>
    );
}
