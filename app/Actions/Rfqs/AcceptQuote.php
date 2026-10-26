<?php

namespace App\Actions\Rfqs;

use App\Enums\QuoteStatus;
use App\Enums\RfqStatus;
use App\Models\Quote;
use App\Models\Rfq;
use App\Notifications\QuoteDecided;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptQuote
{
    /**
     * Award the RFQ to one quote. Every other quote on it is rejected, and
     * each supplier with a quote is told the outcome.
     */
    public function handle(Rfq $rfq, Quote $quote): Quote
    {
        $decided = DB::transaction(function () use ($rfq, $quote) {
            $rfq = Rfq::query()->lockForUpdate()->findOrFail($rfq->id);
            $quote = $rfq->quotes()->whereKey($quote->id)->first();

            if (! $rfq->isOpen()) {
                throw ValidationException::withMessages(['quote' => 'This request has already been decided.']);
            }

            if (! $quote || $quote->status !== QuoteStatus::SUBMITTED) {
                throw ValidationException::withMessages(['quote' => 'This quote can no longer be accepted.']);
            }

            $quote->forceFill(['status' => QuoteStatus::ACCEPTED])->save();
            $rfq->quotes()->whereKeyNot($quote->id)->where('status', QuoteStatus::SUBMITTED)->update(['status' => QuoteStatus::REJECTED]);
            $rfq->forceFill(['status' => RfqStatus::AWARDED])->save();

            return $rfq->quotes()->whereIn('status', [QuoteStatus::ACCEPTED, QuoteStatus::REJECTED])->with('company.owner')->get();
        });

        foreach ($decided as $decidedQuote) {
            $decidedQuote->company->owner->notify(new QuoteDecided($decidedQuote));
        }

        return $quote->refresh();
    }
}
