<?php

namespace App\Jobs;

use App\Models\Rfq;
use App\Models\RfqRecipient;
use App\Notifications\RfqReceived;
use App\Services\Sms\SmsSender;
use App\Support\CurrentTenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells each matched supplier about a new RFQ by database notification,
 * email and SMS, and stamps notified_at (the start of the response-time
 * clock). Recipients already notified are skipped, so a retry does not
 * notify anyone twice.
 */
class NotifyRfqRecipients implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public Rfq $rfq) {}

    public function handle(CurrentTenant $currentTenant, SmsSender $sms): void
    {
        // Queue workers have no marketplace of their own: run as the RFQ's.
        $currentTenant->runAs($this->rfq->tenant, function () use ($sms): void {
            $this->rfq->recipients()
                ->whereNull('notified_at')
                ->with('company.owner')
                ->each(function (RfqRecipient $recipient) use ($sms): void {
                    $recipient->company->owner->notify(new RfqReceived($this->rfq, $recipient->company));

                    $phone = $recipient->company->phone ?? $recipient->company->owner->phone;
                    if ($phone) {
                        try {
                            $sms->send($phone, "New request on {$this->rfq->tenant->name}: {$this->rfq->quantity} {$this->rfq->unit->value} of {$this->rfq->title}. Reply before ".$this->rfq->expires_at->format('j M').'.');
                        } catch (Throwable $exception) {
                            // An SMS failure must not stop the email and in-app notices.
                            Log::warning('RFQ SMS failed', ['rfq' => $this->rfq->id, 'company' => $recipient->company_id, 'error' => $exception->getMessage()]);
                        }
                    }

                    $recipient->update(['notified_at' => now()]);
                });
        });
    }
}
