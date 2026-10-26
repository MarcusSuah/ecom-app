<?php

namespace App\Actions\Reports;

use App\Enums\OrderStatus;
use App\Models\Company;
use App\Models\Order;
use App\Models\Payout;
use App\Models\Quote;
use App\Models\QuoteTier;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Services\Payments\PaymentGateways;
use App\Support\CurrentTenant;
use App\Support\MarketplaceSettings;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as Document;
use Illuminate\Support\Facades\Storage;

/**
 * The PDFs: order invoices and receipts, subscription invoices and payout
 * statements, each branded with its own marketplace (also when rendered
 * from a webhook or a queued email).
 */
class RenderDocument
{
    public function __construct(private CurrentTenant $currentTenant) {}

    /**
     * A receipt once the order is paid, an invoice before.
     */
    public function order(Order $order): Document
    {
        $order->loadMissing(['items', 'buyer', 'seller', 'tenant']);
        // Cash orders are paid when the shop collects the cash (completed).
        $paid = $order->paysCash()
            ? $order->status === OrderStatus::COMPLETED
            : ! in_array($order->status, [OrderStatus::PENDING_PAYMENT, OrderStatus::CANCELLED], true);
        $payment = $paid ? $order->securedPayment() : null;

        return $this->render($order->tenant, 'pdf.order', [
            'title' => ($paid ? 'Receipt ' : 'Invoice ').$order->number,
            'documentType' => $paid ? 'Receipt' : 'Invoice',
            'documentNumber' => $order->number,
            'documentDate' => ($order->placed_at ?? $order->created_at)?->format('j M Y'),
            'order' => $order,
            'paid' => $paid,
            'payment' => $payment,
            'paymentProvider' => $payment ? PaymentGateways::label($payment->provider) : null,
            'protectionLabel' => (string) config('payments.protection_label'),
            'money' => fn (int $amount): string => Money::format($amount, $order->currency),
        ]);
    }

    public function subscriptionInvoice(SubscriptionInvoice $invoice): Document
    {
        $invoice->loadMissing(['subscription.plan', 'subscription.subscriber']);
        $subscriber = $invoice->subscription->subscriber;
        $tenant = $subscriber instanceof Tenant ? $subscriber : Tenant::query()->findOrFail($invoice->tenant_id);

        return $this->render($tenant, 'pdf.subscription-invoice', [
            'title' => "Invoice {$invoice->number}",
            'documentType' => 'Invoice',
            'documentNumber' => $invoice->number,
            'documentDate' => ($invoice->paid_at ?? $invoice->period_starts_at)->format('j M Y'),
            'invoice' => $invoice,
            'subscriberName' => $subscriber->name,
            'subscriberEmail' => $subscriber instanceof Company ? ($subscriber->email ?? $subscriber->owner->email) : $subscriber->support_email,
            'money' => fn (int $amount): string => Money::format($amount, $invoice->currency),
        ]);
    }

    public function payoutStatement(Company $company, ReportPeriod $period): Document
    {
        $payouts = Payout::query()
            ->where('company_id', $company->id)
            ->whereBetween('created_at', [$period->from, $period->to])
            ->with('order:id,number')
            ->orderBy('created_at')
            ->get();

        $totals = $payouts->groupBy('currency')->map(fn ($rows, string $currency) => [
            'currency' => $currency,
            'gross' => Money::format((int) $rows->sum('gross'), $currency),
            'commission' => Money::format((int) $rows->sum('commission'), $currency),
            'net' => Money::format((int) $rows->sum('net'), $currency),
            'paid' => Money::format((int) $rows->whereNotNull('paid_at')->sum('net'), $currency),
            'pending' => Money::format((int) $rows->whereNull('paid_at')->sum('net'), $currency),
        ])->values();

        return $this->render($company->tenant, 'pdf.payout-statement', [
            'title' => "Payout statement {$company->name}",
            'documentType' => 'Payout statement',
            'documentNumber' => $company->name,
            'documentDate' => $period->from->format('j M Y').' – '.$period->to->format('j M Y'),
            'company' => $company,
            'period' => $period,
            'payouts' => $payouts,
            'totals' => $totals,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function render(Tenant $tenant, string $view, array $data): Document
    {
        $settings = $this->currentTenant->runAs($tenant, fn (): array => app(MarketplaceSettings::class)->all());

        return Pdf::loadView($view, [
            'tenant' => $tenant,
            'settings' => $settings,
            // The marketplace logo from Settings heads every document.
            'logo' => self::imageData($settings['logo_path'] ?? null),
            ...$data,
        ])->setPaper('a4');
    }

    /**
     * An image on the public disk as a data URI, which dompdf embeds without
     * reaching the network or the file system outside its root.
     */
    public static function imageData(?string $path): ?string
    {
        $disk = Storage::disk('public');

        if (! $path || ! $disk->exists($path)) {
            return null;
        }

        $mime = $disk->mimeType($path) ?: 'image/png';

        return in_array($mime, ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true)
            ? "data:{$mime};base64,".base64_encode((string) $disk->get($path))
            : null;
    }

    /**
     * A supplier's quote on a request, with its price tiers, the total for
     * the requested quantity, lead time and validity.
     */
    public function quote(Quote $quote): Document
    {
        $quote->loadMissing(['tiers', 'company', 'rfq.category', 'rfq.buyer']);
        $rfq = $quote->rfq;
        $tiers = $quote->tiers->sortBy('min_qty')->values();
        $tier = $tiers->filter(fn (QuoteTier $tier) => $rfq->quantity >= $tier->min_qty)->last();

        return $this->render($quote->company->tenant ?? Tenant::query()->findOrFail($quote->tenant_id), 'pdf.quote', [
            'title' => "Quote {$rfq->title}",
            'documentType' => 'Quote',
            'documentNumber' => 'Q-'.str_pad((string) $quote->id, 6, '0', STR_PAD_LEFT),
            'documentDate' => $quote->created_at?->format('j M Y'),
            'quote' => $quote,
            'tiers' => $tiers,
            'estimate' => $tier ? $tier->unit_price * $rfq->quantity : null,
            'buyerName' => $rfq->contactName(),
            'buyerContact' => $rfq->buyer->email ?? $rfq->guest_email,
            'supplierLogo' => self::imageData($quote->company->logo),
            'money' => fn (int $amount): string => Money::format($amount, $quote->currency),
        ]);
    }
}
