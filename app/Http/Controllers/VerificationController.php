<?php

namespace App\Http\Controllers;

use App\Actions\Verifications\SubmitVerification;
use App\Http\Requests\Verifications\SubmitVerificationRequest;
use App\Models\Verification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VerificationController extends Controller
{
    public function store(SubmitVerificationRequest $request, SubmitVerification $submitVerification): RedirectResponse
    {
        $submitVerification->handle(
            $request->user()->currentCompany(),
            $request->safe()->only(['registration_number', 'notes']),
            $request->documents(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Application sent. We will let you know once it has been reviewed.']);

        return to_route('company.edit');
    }

    /**
     * Stream one document from the private disk to the company owner or a reviewer.
     */
    public function document(Verification $verification, int $index): StreamedResponse
    {
        Gate::authorize('view', $verification);

        $document = $verification->documents[$index] ?? abort(404);
        $disk = Storage::disk(Verification::DOCUMENT_DISK);

        abort_unless($disk->exists($document['path']), 404);

        return $disk->response($document['path'], $document['name'], [
            'Content-Type' => $document['mime'],
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
