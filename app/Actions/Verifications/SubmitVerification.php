<?php

namespace App\Actions\Verifications;

use App\Models\Company;
use App\Models\Verification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class SubmitVerification
{
    /**
     * Apply for verification. Allowed when the company has never applied or
     * its latest application was rejected, so there is never more than one
     * pending application. Documents go to the private disk.
     *
     * @param  array<string, mixed>  $attributes  Validated registration_number and notes
     * @param  list<UploadedFile>  $documents
     */
    public function handle(Company $company, array $attributes, array $documents): Verification
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($company, $attributes, $documents, &$storedPaths): Verification {
                // Serialise submissions for this company so two requests cannot both pass the check.
                $company = Company::query()->lockForUpdate()->findOrFail($company->id);
                $company->load('latestVerification');

                if (! $company->canSubmitVerification()) {
                    throw ValidationException::withMessages([
                        'registration_number' => $company->hasPendingVerification()
                            ? 'Your application is already waiting for review.'
                            : 'Your company is already verified.',
                    ]);
                }

                $stored = [];
                foreach ($documents as $document) {
                    $path = $document->store("verifications/{$company->id}", Verification::DOCUMENT_DISK)
                        ?: throw new RuntimeException('A verification document could not be stored.');
                    $storedPaths[] = $path;
                    $stored[] = [
                        'path' => $path,
                        'name' => $document->getClientOriginalName(),
                        'mime' => (string) $document->getMimeType(),
                        'size' => (int) $document->getSize(),
                    ];
                }

                $verification = new Verification([...$attributes, 'documents' => $stored]);
                $verification->company()->associate($company);
                $verification->save();

                return $verification;
            });
        } catch (Throwable $exception) {
            Storage::disk(Verification::DOCUMENT_DISK)->delete($storedPaths);

            throw $exception;
        }
    }
}
