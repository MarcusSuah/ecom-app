<?php

namespace App\Actions\Verifications;

use App\Enums\VerificationStatus;
use App\Models\User;
use App\Models\Verification;
use App\Notifications\VerificationReviewed;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewVerification
{
    /**
     * Approve or reject a pending application and tell the company owner.
     * Approving the latest application is the only way a company becomes
     * verified. Who may call this is decided by VerificationPolicy::review.
     */
    public function handle(Verification $verification, User $reviewer, VerificationStatus $decision, ?string $rejectionReason = null): Verification
    {
        if ($decision === VerificationStatus::PENDING) {
            throw ValidationException::withMessages(['decision' => 'Choose approve or reject.']);
        }

        if ($decision === VerificationStatus::REJECTED && blank($rejectionReason)) {
            throw ValidationException::withMessages(['rejection_reason' => 'Give the reason for rejecting this application.']);
        }

        $verification = DB::transaction(function () use ($verification, $reviewer, $decision, $rejectionReason): Verification {
            $verification = Verification::query()->lockForUpdate()->findOrFail($verification->id);

            if (! $verification->isPending()) {
                throw ValidationException::withMessages(['decision' => 'This application has already been reviewed.']);
            }

            $verification->status = $decision;
            $verification->reviewer()->associate($reviewer);
            $verification->reviewed_at = now();
            $verification->rejection_reason = $decision === VerificationStatus::REJECTED ? $rejectionReason : null;
            $verification->save();

            return $verification;
        });

        $verification->load('company.owner');
        $verification->company->owner->notify(new VerificationReviewed($verification));

        // The search index stores whether each product's supplier is verified.
        $verification->company->syncProductsToSearch();

        return $verification;
    }
}
