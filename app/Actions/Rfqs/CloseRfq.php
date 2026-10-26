<?php

namespace App\Actions\Rfqs;

use App\Enums\QuoteStatus;
use App\Enums\RfqStatus;
use App\Models\Rfq;
use Illuminate\Validation\ValidationException;

class CloseRfq
{
    /**
     * The buyer stops the request without choosing a quote.
     */
    public function handle(Rfq $rfq): Rfq
    {
        if (! $rfq->isOpen()) {
            throw ValidationException::withMessages(['rfq' => 'Only open requests can be closed.']);
        }

        $rfq->forceFill(['status' => RfqStatus::CLOSED])->save();
        $rfq->quotes()->where('status', QuoteStatus::SUBMITTED)->update(['status' => QuoteStatus::REJECTED]);

        return $rfq;
    }
}
