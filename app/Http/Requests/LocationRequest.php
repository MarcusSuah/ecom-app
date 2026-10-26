<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A delivery location typed in, picked from the neighbourhood list or
 * read from the browser. Shared by the session location and saved addresses.
 */
class LocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:50'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'description' => ['nullable', 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'is_default' => ['boolean'],
        ];
    }

    /**
     * @return array{label: string, lat: float, lng: float, description: string|null, landmark: string|null}
     */
    public function location(): array
    {
        return [
            'label' => $this->string('label')->toString(),
            'lat' => $this->float('lat'),
            'lng' => $this->float('lng'),
            'description' => $this->filled('description') ? $this->string('description')->toString() : null,
            'landmark' => $this->filled('landmark') ? $this->string('landmark')->toString() : null,
        ];
    }
}
