<?php

namespace App\Actions\Rfqs;

use App\Enums\RfqStatus;
use App\Jobs\NotifyRfqRecipients;
use App\Models\Company;
use App\Models\Rfq;
use App\Models\User;
use App\Notifications\GuestRfqPosted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class CreateRfq
{
    public function __construct(private MatchSuppliersForRfq $matchSuppliers) {}

    /**
     * Post a request, record the matching suppliers and queue their
     * notifications. Open for 14 days.
     *
     * A guest request has no buyer account: it keeps the guest's contact
     * details and a private link, emailed to them, to follow the quotes.
     * It joins their account when they sign in with the same email.
     *
     * @param  array<string, mixed>  $attributes  Validated RFQ fields
     * @param  array{name: string, email: string, phone?: string|null}|null  $guest
     * @param  bool  $onlyProductSupplier  Send it to the product's supplier only (an inquiry about a listing).
     */
    public function handle(?User $buyer, array $attributes, ?array $guest = null, bool $onlyProductSupplier = false): Rfq
    {
        $rfq = DB::transaction(function () use ($buyer, $attributes, $guest, $onlyProductSupplier): Rfq {
            $rfq = new Rfq($attributes);

            if ($buyer) {
                $rfq->buyer()->associate($buyer);
            } else {
                $rfq->forceFill([
                    'guest_name' => $guest['name'] ?? null,
                    'guest_email' => isset($guest['email']) ? Str::lower(trim($guest['email'])) : null,
                    'guest_phone' => $guest['phone'] ?? null,
                    'access_token' => Str::random(48),
                ]);
            }

            $rfq->status = RfqStatus::OPEN;
            $rfq->expires_at = now()->addDays(Rfq::DEFAULT_LIFETIME_DAYS);
            $rfq->save();

            $rfq->recipients()->createMany(
                $this->matchSuppliers->handle($rfq, $onlyProductSupplier)
                    ->map(fn (Company $company) => ['company_id' => $company->id])
                    ->all(),
            );

            return $rfq;
        });

        NotifyRfqRecipients::dispatch($rfq)->afterCommit();

        if ($rfq->isGuest()) {
            Notification::route('mail', [$rfq->guest_email => $rfq->guest_name])->notify(new GuestRfqPosted($rfq));
        }

        return $rfq;
    }
}
