<?php

namespace App\Http\Controllers;

use App\Actions\Reports\RenderDocument;
use App\Actions\Reports\ReportPeriod;
use App\Models\Company;
use App\Models\Order;
use App\Models\Quote;
use App\Models\SubscriptionInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * PDF downloads. Each checks who may see the document before rendering it.
 */
class DocumentController extends Controller
{
    public function order(Order $order, RenderDocument $render): Response
    {
        Gate::authorize('document', $order);

        return $render->order($order)->download("{$order->number}.pdf");
    }

    /**
     * A seller's plan invoice (its owner, or staff managing seller plans) or
     * the marketplace's own platform invoice (billing.manage).
     */
    public function subscriptionInvoice(Request $request, SubscriptionInvoice $invoice, RenderDocument $render): Response
    {
        $user = $request->user();
        $subscriber = $invoice->subscription->subscriber;

        $allowed = $subscriber instanceof Company
            ? $subscriber->owner_id === $user->id || $user->can('seller-subscriptions.manage')
            : $user->can('billing.manage');

        abort_unless($allowed, 403);

        return $render->subscriptionInvoice($invoice)->download("{$invoice->number}.pdf");
    }

    /**
     * A quote as PDF: for the buyer who asked, the supplier who quoted
     * (or the super admin managing it), and staff who see reports.
     */
    public function quote(Request $request, Quote $quote, RenderDocument $render): Response
    {
        $user = $request->user();
        $quote->loadMissing(['rfq', 'company']);

        abort_unless(
            $quote->rfq->buyer_id === $user->id || $user->managesCompany($quote->company) || $user->can('reports.view'),
            403,
        );

        return $render->quote($quote)->download('quote-'.Str::slug($quote->company->name).'-'.$quote->id.'.pdf');
    }

    public function payoutStatement(Request $request, RenderDocument $render): Response
    {
        $company = $request->user()->currentCompany() ?? abort(403);
        $period = ReportPeriod::fromRequest($request);

        return $render->payoutStatement($company, $period)->download('payouts-'.Str::slug($company->name)."-{$period->slug()}.pdf");
    }
}
