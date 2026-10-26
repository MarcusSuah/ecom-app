<?php

namespace App\Actions\Quotes;

use App\Enums\QuoteStatus;
use App\Models\Quote;
use Illuminate\Validation\ValidationException;

class WithdrawQuote
{
    public function handle(Quote $quote): Quote
    {
        if ($quote->status !== QuoteStatus::SUBMITTED || ! $quote->rfq->isOpen()) {
            throw ValidationException::withMessages(['quote' => 'Only quotes on open requests can be withdrawn.']);
        }

        $quote->forceFill(['status' => QuoteStatus::WITHDRAWN])->save();

        return $quote;
    }
}
