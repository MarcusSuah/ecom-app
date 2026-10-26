<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Verification;

class VerificationPolicy
{
    /**
     * The review queue: verifiers and admins.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('verifications.review');
    }

    /**
     * Reviewers, and the owner of the company that applied. Covers the
     * documents too: owners never see another company's documents.
     */
    public function view(User $user, Verification $verification): bool
    {
        return $user->can('verifications.review')
            || $verification->company->owner_id === $user->id;
    }

    public function review(User $user, Verification $verification): bool
    {
        return $user->can('verifications.review');
    }
}
