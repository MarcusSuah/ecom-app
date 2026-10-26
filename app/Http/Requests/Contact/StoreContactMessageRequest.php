<?php

namespace App\Http\Requests\Contact;

use App\Enums\ContactTopic;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Anyone may write in. "website" is a honeypot that people never see, so
 * a value there means a bot filled the form.
 */
class StoreContactMessageRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]{7,30}$/'],
            'topic' => ['required', Rule::enum(ContactTopic::class)],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'website' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Tell us your name.',
            'email.required' => 'Enter an email address so we can reply.',
            'phone.regex' => 'Enter a phone number with digits only, e.g. +250 788 123 456.',
            'topic.required' => 'Choose what your message is about.',
            'message.required' => 'Write your message.',
            'message.min' => 'Tell us a little more (at least 10 characters).',
            'website.prohibited' => 'Your message could not be sent.',
        ];
    }
}
