<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Where accounts waiting for approval (or turned down) land after signing in.
 */
class AccountApprovalController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->isPlatformAdmin() || ! ($user->isPending() || $user->isRejected())) {
            return redirect()->route('home');
        }

        return Inertia::render('auth/pending-approval', [
            'status' => $user->status->value,
            'reason' => $user->isRejected() ? $user->rejection_reason : null,
            'email' => $user->email,
        ]);
    }
}
