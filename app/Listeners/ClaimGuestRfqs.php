<?php

namespace App\Listeners;

use App\Models\Rfq;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Str;

/**
 * Requests a guest posted with an email join that person's account once
 * they sign in with it, on every marketplace. Only verified addresses
 * count, so nobody can collect quotes by registering someone else's email.
 */
class ClaimGuestRfqs
{
    public function handle(Login|Verified $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || $user->email_verified_at === null) {
            return;
        }

        Rfq::query()
            ->withoutGlobalScopes()
            ->whereNull('buyer_id')
            ->where('guest_email', Str::lower($user->email))
            ->update(['buyer_id' => $user->id]);
    }
}
