<?php

namespace App\Actions\Rfqs;

use App\Enums\RfqStatus;
use App\Models\Rfq;

class ExpireRfqs
{
    /**
     * Mark open requests past their expiry as expired, on every marketplace.
     * Quotes keep their status so suppliers can still see what they sent.
     */
    public function handle(): int
    {
        return Rfq::withoutGlobalScopes()
            ->where('status', RfqStatus::OPEN)
            ->where('expires_at', '<=', now())
            ->update(['status' => RfqStatus::EXPIRED, 'updated_at' => now()]);
    }
}
