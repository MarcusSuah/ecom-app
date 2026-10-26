<?php

namespace App\Notifications;

use App\Enums\VerificationStatus;
use App\Models\Verification;
use Illuminate\Notifications\Notification;

class VerificationReviewed extends Notification
{
    public function __construct(public Verification $verification) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return $this->verification->status === VerificationStatus::APPROVED
            ? 'verification.approved'
            : 'verification.rejected';
    }

    /**
     * Users are shared across marketplaces, so the tenant is recorded to show
     * the notification only on the marketplace it belongs to.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $company = $this->verification->company;
        $approved = $this->verification->status === VerificationStatus::APPROVED;

        return [
            'tenant_id' => $this->verification->tenant_id,
            'title' => $approved ? 'Your company is verified' : 'Verification application rejected',
            'message' => $approved
                ? "{$company->name} now shows the Verified badge."
                : "{$company->name}'s application was rejected: {$this->verification->rejection_reason}",
            'link_url' => route('company.edit', absolute: false),
            'verification_id' => $this->verification->id,
        ];
    }
}
