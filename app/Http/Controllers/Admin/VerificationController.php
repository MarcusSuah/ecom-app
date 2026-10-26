<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Verifications\ReviewVerification;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Verification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class VerificationController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Verification::class);

        $status = VerificationStatus::tryFrom($request->string('status')->toString()) ?? VerificationStatus::PENDING;

        $verifications = Verification::query()
            ->with(['company:id,name,slug,type,city,country', 'reviewer:id,name'])
            ->where('status', $status)
            // The pending queue is first come, first served; history shows the newest first.
            ->when($status === VerificationStatus::PENDING, fn ($query) => $query->oldest('id'), fn ($query) => $query->latest('id'))
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Verification $verification) => [
                ...$verification->toSummary(),
                'company' => [
                    'name' => $verification->company->name,
                    'type' => $verification->company->type->label(),
                    'city' => $verification->company->city,
                    'country' => $verification->company->country,
                ],
            ]);

        return Inertia::render('Admin/Verifications/Index', [
            'verifications' => $verifications,
            'filters' => ['status' => $status->value],
        ]);
    }

    public function show(Verification $verification): Response
    {
        Gate::authorize('review', $verification);

        $verification->load(['company.owner:id,name,email', 'reviewer:id,name']);
        $company = $verification->company;

        return Inertia::render('Admin/Verifications/Show', [
            'verification' => $verification->toSummary(),
            'company' => [
                ...$company->toSummary(),
                'description' => $company->description,
                'phone' => $company->phone,
                'email' => $company->email,
                'owner' => ['name' => $company->owner->name, 'email' => $company->owner->email],
            ],
            'history' => $company->verifications()
                ->whereKeyNot($verification->id)
                ->with('reviewer:id,name')
                ->latest('id')
                ->get()
                ->map(fn (Verification $previous) => $previous->toSummary()),
        ]);
    }

    public function review(Request $request, Verification $verification, ReviewVerification $reviewVerification): RedirectResponse
    {
        Gate::authorize('review', $verification);

        $validated = $request->validate([
            'decision' => ['required', Rule::in([VerificationStatus::APPROVED->value, VerificationStatus::REJECTED->value])],
            'rejection_reason' => ['required_if:decision,rejected', 'nullable', 'string', 'max:2000'],
        ], [
            'rejection_reason.required_if' => 'Give the reason for rejecting this application.',
        ]);

        $decision = VerificationStatus::from($validated['decision']);
        $reviewVerification->handle($verification, $request->user(), $decision, $validated['rejection_reason'] ?? null);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $decision === VerificationStatus::APPROVED ? 'Company verified.' : 'Application rejected.',
        ]);

        return to_route('admin.verifications.index');
    }
}
