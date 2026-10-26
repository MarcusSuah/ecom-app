<?php

namespace App\Http\Requests\Verifications;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

class SubmitVerificationRequest extends FormRequest
{
    public const MAX_DOCUMENTS = 5;

    public function authorize(): bool
    {
        $company = $this->user()->currentCompany();

        return $company !== null && $this->user()->can('submitVerification', $company);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'registration_number' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'documents' => ['required', 'array', 'min:1', 'max:'.self::MAX_DOCUMENTS],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    /**
     * @return list<UploadedFile>
     */
    public function documents(): array
    {
        return array_values(Arr::wrap($this->file('documents', [])));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'documents.required' => 'Attach at least one registration document.',
            'documents.*.mimes' => 'Documents must be PDF, JPG or PNG files.',
            'documents.*.max' => 'Each document must be 5 MB or smaller.',
        ];
    }
}
