import { ContentPage, type ContentPageProps } from '@/components/content-page';
import { DeliveryFeeExplainer } from '@/components/shop-card';

export default function ShippingAndCustoms({ title, facts }: ContentPageProps) {
    return (
        <ContentPage
            title={title}
            current="shipping-and-customs"
            protectionLabel={facts.protectionLabel}
            lead="How orders reach you, what delivery costs and what to know when goods cross a border."
        >
            <section>
                <h2>Local shop delivery</h2>
                <DeliveryFeeExplainer roadFactor={facts.roadFactor} />
                <p>
                    You see the exact fee for your location before you pay, and
                    you can always choose pickup instead if the shop offers it.
                </p>
            </section>

            <section>
                <h2>Wholesale shipping</h2>
                <ul>
                    <li>
                        Each product shows its lead time: how long the supplier
                        needs before the goods are ready to ship.
                    </li>
                    <li>
                        Delivery terms and cost are agreed in the quote or in
                        chat with the supplier. Ask for them in writing before
                        you pay.
                    </li>
                    <li>
                        The order moves from "in production" to "shipped";
                        confirm receipt once the goods arrive and you have
                        checked them.
                    </li>
                </ul>
            </section>

            <section>
                <h2>Imports and customs</h2>
                <p>
                    Products show their country of origin. For goods from
                    outside Rwanda, import duties, VAT and clearing fees are
                    usually paid by the importer unless the quote says
                    otherwise. Check with the supplier who handles clearing, and
                    keep the commercial invoice and packing list they send.
                </p>
                <p>
                    Within the East African Community, goods with a certificate
                    of origin may qualify for reduced duties. Ask the supplier
                    for one when you order.
                </p>
            </section>

            <section>
                <h2>Damaged or missing goods</h2>
                <p>
                    Check deliveries before confirming receipt. If anything is
                    damaged or missing, open a dispute from the order page
                    before the payment is released (within{' '}
                    {facts.autoReleaseDays} days of delivery), with photos if
                    you can.
                </p>
            </section>
        </ContentPage>
    );
}
