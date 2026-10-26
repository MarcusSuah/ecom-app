<?php

namespace App\Listeners;

use App\Models\User;
use App\Models\UserActivity;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;

/**
 * Sign-ins, sign-outs, failed attempts and password resets in the activity
 * log. A failed attempt keeps the email that was typed, never the password.
 */
class RecordAuthActivity
{
    public function __construct(private Request $request) {}

    public function handle(Login|Logout|Failed|PasswordReset $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        match (true) {
            $event instanceof Login => $this->login($user),
            $event instanceof Logout => ActivityLogger::log($this->request, 'logout', 'Signed out', $user),
            $event instanceof PasswordReset => ActivityLogger::log($this->request, 'password_changed', 'Reset the password', $user),
            $event instanceof Failed => ActivityLogger::log($this->request, 'login_failed', 'Failed sign-in', $user, [
                'email' => mb_substr((string) ($event->credentials['email'] ?? ''), 0, 255) ?: null,
                'is_suspicious' => $this->repeatedFailures(),
            ]),
        };
    }

    private function login(?User $user): void
    {
        ActivityLogger::log($this->request, 'login', 'Signed in', $user);

        $user?->forceFill(['last_login_at' => now(), 'last_login_ip' => $this->request->ip()])->saveQuietly();
    }

    /**
     * Five or more failures from this address in the last 15 minutes.
     */
    private function repeatedFailures(): bool
    {
        return UserActivity::query()
            ->where('event', 'login_failed')
            ->where('ip_address', $this->request->ip())
            ->where('created_at', '>=', now()->subMinutes(15))
            ->count() >= 4;
    }
}
