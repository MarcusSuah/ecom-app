import {
    Banknote,
    Landmark,
    Percent,
    ReceiptText,
    Undo2,
    Wallet,
} from 'lucide-react';
import { KpiCard } from '@/components/dashboard/charts';

export type SalesTotals = {
    currency: string;
    /** Some amounts were converted into this currency at the rate in use. */
    converted?: boolean;
    orders: number;
    gross: string;
    average: string | null;
    /** VAT included in the sales, at the marketplace's rate. */
    vat: string;
    vat_percent: number;
    refunds: number;
    refunded: string;
    commission: string;
    net: string;
    paid_out: string;
    pending: string;
};

/**
 * One row of figures per currency: amounts in different currencies are
 * never added together.
 */
export function SalesTotalsCards({
    totals,
    netLabel = 'Seller earnings',
}: {
    totals: SalesTotals[];
    netLabel?: string;
}) {
    if (totals.length === 0) {
        return (
            <p className="rounded-md border p-8 text-center text-sm text-muted-foreground">
                No sales in this period.
            </p>
        );
    }

    return (
        <div className="space-y-6">
            {totals.map((row) => (
                <section
                    key={row.currency}
                    aria-label={`Totals in ${row.currency}`}
                    className="space-y-2"
                >
                    {(totals.length > 1 || row.converted) && (
                        <h2 className="font-mono text-xs font-medium text-muted-foreground">
                            {row.currency}
                            {row.converted && ' · converted at the rate in use'}
                        </h2>
                    )}
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <KpiCard
                            icon={Banknote}
                            label="Sales"
                            value={row.gross}
                            hint={`${row.orders.toLocaleString()} orders${row.average ? ` · ${row.average} average` : ''}`}
                        />
                        <KpiCard
                            icon={Percent}
                            label="Commission"
                            value={row.commission}
                            hint="On released payments"
                        />
                        <KpiCard
                            icon={Wallet}
                            label={netLabel}
                            value={row.net}
                            hint={`${row.paid_out} paid out`}
                        />
                        <KpiCard
                            icon={ReceiptText}
                            label="Payouts pending"
                            value={row.pending}
                            hint="Released, not yet paid out"
                        />
                        <KpiCard
                            icon={Landmark}
                            label="VAT in sales"
                            value={row.vat}
                            hint={`At ${row.vat_percent}%, included in prices`}
                        />
                        <KpiCard
                            icon={Undo2}
                            label="Refunded"
                            value={row.refunded}
                            hint={`${row.refunds.toLocaleString()} orders`}
                        />
                    </div>
                </section>
            ))}
        </div>
    );
}
