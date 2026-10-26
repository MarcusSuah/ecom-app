<?php

namespace App\Actions\Rfqs;

use App\Enums\QuoteStatus;
use App\Models\RfqRecipient;
use Illuminate\Validation\ValidationException;

class DeclineRfq
{
    /**
     * A supplier says it will not quote. Not possible once it has an active quote.
     */
    public function handle(RfqRecipient $recipient, string $reason): RfqRecipient
    {
        $hasActiveQuote = $recipient->rfq->quotes()
            ->where('company_id', $recipient->company_id)
            ->whereIn('status', [QuoteStatus::SUBMITTED, QuoteStatus::ACCEPTED])
            ->exists();

        if ($hasActiveQuote) {
            throw ValidationException::withMessages(['reason' => 'Withdraw your quote before declining this request.']);
        }

        $recipient->update([
            'viewed_at' => $recipient->viewed_at ?? now(),
            'declined_at' => now(),
            'decline_reason' => $reason,
        ]);

        return $recipient;
    }
}
