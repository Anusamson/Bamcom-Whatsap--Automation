<?php

namespace App\Http\Requests\Contact;

use App\Enums\ContactStatus;
use App\Enums\LeadSource;
use App\Services\Contact\PhoneNormalizerService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CreateContactRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize the phone number before validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('phone') && is_string($this->phone)) {
            $this->merge([
                'phone' => app(PhoneNormalizerService::class)->normalize($this->phone),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30', 'unique:contacts,phone'],
            'email' => ['nullable', 'email', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:150'],
            'preferred_language' => ['nullable', 'string', 'max:20'],
            'lead_source' => ['nullable', new Enum(LeadSource::class)],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', new Enum(ContactStatus::class)],
            'last_contact_at' => ['nullable', 'date'],
        ];
    }

    /**
     * Custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.unique' => 'This phone number has already been registered for another CRM contact.',
            'phone.required' => 'A valid contact phone number is required.',
        ];
    }
}
