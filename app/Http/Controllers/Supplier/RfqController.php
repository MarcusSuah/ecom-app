<?php

namespace App\Http\Controllers\Supplier;

use App\Actions\Quotes\SubmitQuote;
use App\Actions\Quotes\WithdrawQuote;
use App\Actions\Rfqs\DeclineRfq;
use App\Enums\PlanLimit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Quotes\SubmitQuoteRequest;
use App\Models\Company;
use App\Models\Quote;
use App\Models\QuoteTier;
use App\Models\Rfq;
use App\Models\RfqRecipient;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Requests for quotation a supplier has received.
 */
class RfqController extends Controller
{
    public function index(Request $request): Response
    {
        $company = $this->company($request);

        $recipients = RfqRecipient::query()
            ->where('company_id', $company->id)
            ->whereHas('rfq')
            ->with(['rfq.category:id,name'])
            ->latest('id')
            ->paginate(20);

        $quotes = Quote::query()
            ->where('company_id', $company->id)
            ->whereIn('rfq_id', $recipients->getCollection()->pluck('rfq_id'))
            ->get()
            ->keyBy('rfq_id');

        $recipients->through(function (RfqRecipient $recipient) use ($quotes) {
            $quote = $quotes->get($recipient->rfq_id);

            return [
                'rfq_id' => $recipient->rfq_id,
                'title' => $recipient->rfq->title,
                'quantity' => $recipient->rfq->quantity,
                'unit' => $recipient->rfq->unit->value,
                'category' => $recipient->rfq->category->name,
                'deliver_city' => $recipient->rfq->deliver_city,
                'rfq_status' => $recipient->rfq->status->value,
                'expires_at' => $recipient->rfq->expires_at->toISOString(),
                'is_new' => $recipient->viewed_at === null,
                'declined' => $recipient->declined_at !== null,
                'quote_status' => $quote?->status->value,
            ];
        });

        return Inertia::render('supplier/rfqs/index', [
            'requests' => $recipients,
            'quotesThisMonth' => Quote::query()->where('company_id', $company->id)->where('created_at', '>=', now()->startOfMonth())->count(),
            'monthlyLimit' => $company->planLimit(PlanLimit::MONTHLY_QUOTES),
        ]);
    }

    /**
     * Opening a request marks it as viewed.
     */
    public function show(Request $request, Rfq $rfq): Response
    {
        Gate::authorize('respond', $rfq);

        $company = $this->company($request);
        $recipient = $rfq->recipientFor($company) ?? abort(404);

        if ($recipient->viewed_at === null) {
            $recipient->update(['viewed_at' => now()]);
        }

        $rfq->load('category:id,name', 'product:id,name,slug');
        $quote = $rfq->quotes()->where('company_id', $company->id)->with('tiers')->first();

        return Inertia::render('supplier/rfqs/show', [
            'rfq' => [
                'id' => $rfq->id,
                'title' => $rfq->title,
                'quantity' => $rfq->quantity,
                'unit' => $rfq->unit->value,
                'category' => $rfq->category->name,
                'product' => $rfq->product ? ['name' => $rfq->product->name, 'slug' => $rfq->product->slug] : null,
                'deliver_city' => $rfq->deliver_city,
                'needed_by' => $rfq->needed_by?->toDateString(),
                'notes' => $rfq->notes,
                'status' => $rfq->status->value,
                'is_open' => $rfq->isOpen() && $rfq->expires_at->isFuture(),
                'expires_at' => $rfq->expires_at->toISOString(),
            ],
            'declined' => $recipient->declined_at !== null,
            'quote' => $quote ? [
                'id' => $quote->id,
                'status' => $quote->status->value,
                'currency' => $quote->currency,
                'lead_time_min_days' => $quote->lead_time_min_days,
                'lead_time_max_days' => $quote->lead_time_max_days,
                'sample_terms' => $quote->sample_terms ?? '',
                'valid_until' => $quote->valid_until->toDateString(),
                'message' => $quote->message ?? '',
                'tiers' => $quote->tiers->map(fn (QuoteTier $tier) => [
                    'min_qty' => $tier->min_qty,
                    'unit_price' => Money::toMajor($tier->unit_price, $quote->currency),
                ])->values(),
            ] : null,
            'defaults' => [
                'currency' => $company->tenant->default_currency,
                'valid_until' => now()->addDays(30)->toDateString(),
            ],
            'currencies' => Money::currencies(),
            'maxTiers' => SubmitQuoteRequest::MAX_TIERS,
        ]);
    }

    public function decline(Request $request, Rfq $rfq, DeclineRfq $declineRfq): RedirectResponse
    {
        Gate::authorize('respond', $rfq);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']], [
            'reason.required' => 'Tell the buyer why you are declining.',
        ]);
        $declineRfq->handle($rfq->recipientFor($this->company($request)) ?? abort(404), $validated['reason']);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Request declined.']);

        return to_route('supplier.rfqs.index');
    }

    public function saveQuote(SubmitQuoteRequest $request, Rfq $rfq, SubmitQuote $submitQuote): RedirectResponse
    {
        $submitQuote->handle($rfq, $this->company($request), $request->quoteAttributes(), $request->tiers());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Quote sent. You can edit it until the buyer decides.']);

        return to_route('supplier.rfqs.show', $rfq);
    }

    public function withdraw(Request $request, Rfq $rfq, WithdrawQuote $withdrawQuote): RedirectResponse
    {
        Gate::authorize('respond', $rfq);

        $quote = $rfq->quotes()->where('company_id', $this->company($request)->id)->first() ?? abort(404);
        $withdrawQuote->handle($quote);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Quote withdrawn.']);

        return back();
    }

    private function company(Request $request): Company
    {
        return $request->user()->currentCompany() ?? abort(404);
    }
}
