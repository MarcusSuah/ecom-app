<?php

namespace App\Actions\Quotes;

use App\Enums\PlanLimit;
use App\Enums\QuoteStatus;
use App\Models\Company;
use App\Models\Quote;
use App\Models\Rfq;
use App\Notifications\QuoteReceived;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class SubmitQuote
{
    /**
     * Send or edit a supplier's quote on an RFQ it received. One quote per
     * supplier per RFQ, editable (or re-sent after withdrawing) until the
     * RFQ is awarded or closed. New quotes count against the seller plan's
     * monthly quote limit. Tiers must already pass QuoteTierRules.
     *
     * @param  array<string, mixed>  $attributes  Validated quote fields
     * @param  list<array{min_qty: int, unit_price: int}>  $tiers  Prices in minor units
     */
    public function handle(Rfq $rfq, Company $company, array $attributes, array $tiers): Quote
    {
        if ($tiers === []) {
            throw new InvalidArgumentException('A quote needs at least one price tier.');
        }

        $isNew = false;

        $quote = DB::transaction(function () use ($rfq, $company, $attributes, $tiers, &$isNew): Quote {
            $rfq = Rfq::query()->lockForUpdate()->findOrFail($rfq->id);

            if (! $rfq->isOpen() || $rfq->expires_at->isPast()) {
                throw ValidationException::withMessages(['rfq' => 'This request is no longer accepting quotes.']);
            }

            $recipient = $rfq->recipientFor($company);

            if (! $recipient) {
                throw ValidationException::withMessages(['rfq' => 'You can only quote on requests sent to you.']);
            }

            $quote = $rfq->quotes()->where('company_id', $company->id)->first();

            if (! $quote) {
                if ($company->effectivePlan() === null) {
                    throw ValidationException::withMessages(['rfq' => 'Choose a seller plan before sending quotes.']);
                }

                $sentThisMonth = Quote::query()
                    ->where('company_id', $company->id)
                    ->where('created_at', '>=', now()->startOfMonth())
                    ->count();

                if (! $company->withinPlanLimit(PlanLimit::MONTHLY_QUOTES, $sentThisMonth)) {
                    throw ValidationException::withMessages([
                        'rfq' => "Your plan allows {$company->planLimit(PlanLimit::MONTHLY_QUOTES)} quotes a month. Upgrade your plan to send more.",
                    ]);
                }

                $quote = new Quote;
                $quote->rfq()->associate($rfq);
                $quote->company()->associate($company);
                $isNew = true;
            }

            $quote->fill($attributes);
            $quote->status = QuoteStatus::SUBMITTED;
            $quote->save();

            $quote->tiers()->delete();
            $quote->tiers()->createMany($tiers);

            // Replying also counts as having seen the request; a quote cancels a decline.
            $recipient->update([
                'viewed_at' => $recipient->viewed_at ?? now(),
                'declined_at' => null,
                'decline_reason' => null,
            ]);

            return $quote->load('tiers');
        });

        // Edits are not announced; the first quote from each supplier is.
        if ($isNew) {
            $quote->rfq->notifyBuyer(new QuoteReceived($quote->loadMissing('company')));
        }

        return $quote;
    }
}
