<?php

namespace App\Actions\Disputes;

use App\Actions\Payments\RefundPayment;
use App\Actions\Payments\ReleasePayment;
use App\Enums\DisputeStatus;
use App\Models\Dispute;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResolveDispute
{
    public function __construct(
        private ReleasePayment $releasePayment,
        private RefundPayment $refundPayment,
    ) {}

    /**
     * An admin decides: release the money to the seller, or refund the buyer.
     */
    public function handle(Dispute $dispute, User $admin, DisputeStatus $outcome, string $note): Dispute
    {
        if ($outcome === DisputeStatus::OPEN) {
            throw ValidationException::withMessages(['outcome' => 'Choose release or refund.']);
        }

        return DB::transaction(function () use ($dispute, $admin, $outcome, $note): Dispute {
            $dispute = Dispute::query()->lockForUpdate()->findOrFail($dispute->id);

            if (! $dispute->isOpen()) {
                throw ValidationException::withMessages(['outcome' => 'This dispute has already been resolved.']);
            }

            $order = $dispute->order;

            if ($outcome === DisputeStatus::RESOLVED_RELEASE) {
                $this->releasePayment->handle($order, $admin, "Dispute resolved for the seller: {$note}", resolvingDispute: true);
            } else {
                $this->refundPayment->handle($order, $admin, "Dispute resolved for the buyer: {$note}");
            }

            $dispute->forceFill([
                'status' => $outcome,
                'resolved_by' => $admin->id,
                'resolution_note' => $note,
                'resolved_at' => now(),
            ])->save();

            return $dispute;
        });
    }
}
